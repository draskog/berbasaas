# Harvest Import: Skip Reasons & Record Tracking

Детаљан преглед свих разлога зашто се записи избацују или стављају у staging.

---

## Фазе обраде CSV-а

### Фаза 1: Прочитавање CSV редова

За сваки редак из CSV фајла, проверава се:

#### ✂️ `skippedEmpty` - Избачени редови
- **Услов**: `empty($row)` ИЛИ `!isset($row[$productCol])`
- **Значење**: 
  - Редак је потпуно празан
  - Редак нема обавезну колону "Product" (Harvester Number)
- **Евидентирање**: Само број, нису сачувани у базу
- **Брајање**: Укупан CSV редове = $rowCount + $skippedEmpty + $skippedInvalidDate
- **Лог**: Без логовања

#### ✂️ `skippedInvalidDate` - Невалидан датум/време
- **Услов**: `parseDateTime($dateStr, $timeStr)` враћа `null`
- **Значење**: Датум и време не могу бити парсирани
  - Допуштени формати:
    - `Y-m-d H:i:s` (нпр. "2025-06-21 14:30:45")
    - `d-m-y H:i:s` (нпр. "21-06-25 14:30:45")
  - Неисправни примери:
    - "25:61:45" - невалидно време
    - "2025-13-45" - невалидан датум
    - Пусти вредности
- **Евидентирање**: Само број, нису сачувани у базу
- **Лог**: `ℹ️ WARNING` са детаљима: датум, време, формат, грешка
- **Конкретан проблем**: За 21-06-2025, вероватно ~826 записа не парсира датум

---

### Фаза 2: Филтрирање дупликата

После парсирања, записи се проверавају за дупликате унутар и ван CSV-а.

#### 🔁 `in_file_duplicate` - Дупликат унутар CSV-а
- **Услов**: Исти запис види се више пута унутар истог CSV фајла
- **Провера**: По кључу `(company_id|product_id|harvester_number|weighed_at)`
- **Статус**: `invalid` (у staging_records)
- **Validation reason**: `["in_file_duplicate"]`
- **Корисник**: Могу се избрисати у "Resolve" процесу
- **Auto-resolve**: Аутоматски бришу се на `autoResolve()`

#### 🔁 `db_duplicate` - Дупликат из базе
- **Услов**: Исти запис већ постоји у:
  - `harvest_records` (већ импортовано)
  - `harvest_record_staging` (већ у staging)
- **Провера**: По истом кључу
- **Статус**: `invalid` (у staging_records)
- **Validation reason**: `["db_duplicate"]`
- **Видљивост**: Колона "Duplicates" у табели
- **Корисник**: Могу се избрисати
- **Auto-resolve**: Аутоматски бришу се на `autoResolve()`

---

### Фаза 3: Валидација записа

Записи који су успешно парсирани и нису дупликати, сада се валидирају.

#### ⚠️ `tare_out_of_range` - Тара изван дозвољеног опсега
- **Услов**: 
  - `tare < $settings->tare_min` ИЛИ
  - `tare > $settings->tare_max`
- **Значење**: Вредност тара не спада у дозвољени опсег
- **Подешавање**: Преко `HarvestImportSettings` по компанији
- **Статус**: `invalid` (у staging_records)
- **Validation reason**: `["tare_out_of_range"]`
- **Auto-detect**: Ако нема settings, проверава се:
  - Ако су неки записи имали tare=0 и неки имају tare>0
  - Тада се flag-уј сви tare=0 записи
- **Корисник**: Могу се исправити са `autoResolve()` ако има suggestion
- **Hint**: Користи tare вредност из следећег sequential записа

#### ❌ `harvester_not_found` - Берач није регистрован
- **Услов**: Нема `HarvesterAssignment` за:
  - Годину: `weighed_at->year`
  - Број: `harvester_number`
  - Компануја: `company_id`
- **Значење**: Берач са том бројком није регистрован за ту годину
- **Статус**: `invalid` (у staging_records)
- **Validation reason**: `["harvester_not_found"]`
- **Решење**: Мора додати берача у `HarvesterAssignment`
- **Корисник**: Не могу се автоматски исправити
- **Пример**: За 21-06-2025, сви 17 су `harvester_not_found`

---

## Крајни исход

| Категорија | Мест | Видљивост | Могућност исправе |
|-----------|------|-----------|------------------|
| **skippedEmpty** | Избачено | Само број у record_count | ❌ Не |
| **skippedInvalidDate** | Избачено | Само број + LOG | ❌ Не (мора fix CSV) |
| **in_file_duplicate** | staging | ❌ Скривено | ✅ Избриши |
| **db_duplicate** | staging | ✅ "Duplicates" колона | ✅ Избриши |
| **tare_out_of_range** | staging | ❌ Скривено | ✅ Auto-resolve |
| **harvester_not_found** | staging | ❌ Скривено | ❌ Мора додати берача |
| ✅ **Valid** | harvest_records | ✅ "Valid" број | - |

---

## Пример: 21-06-2025 анализа

```
CSV редова:        843
├─ skippedEmpty:   0 (нема празних)
├─ skippedInvalidDate: 826 ❌ (ПРОБЛЕМ: датум/време неважећи)
└─ Парсирано:      17
   ├─ in_file_duplicate: 0
   ├─ db_duplicate: 0
   └─ Валидно парсирано: 17
      ├─ harvester_not_found: 17 ⚠️ (мора додати берање)
      └─ Промовирано у harvest_records: 0
```

**Статус**: ❌ Неисправно (0 valid)
- Од 843 записа, само 17 је парсирано (826 jer invalid datetime)
- Од тих 17, ниједан није valid (сви harvester_not_found)

---

## Како добити детаљне информације

### 1. Види скиповане записе у логу
```bash
vendor/bin/sail logs laravel.test | grep "Failed to parse datetime"
```

### 2. Види записе у staging
```bash
vendor/bin/sail artisan tinker
$upload = \App\Models\HarvestUpload::where('original_filename', '21-06-2025.csv')->first();
$upload->stagingRecords()->select('validation_reason')->distinct()->get();
```

### 3. Види дупликате у колони
- Отворити UI
- Погледај "Duplicates" колону

---

## Резиме за коришћење

- ✂️ **Избачени записи** (skipped) = CSV редови са проблемима пре парсирања
  - Нису видљиви негде
  - Мора fix CSV фајла
  - Репортовати број у `record_count`

- ⚠️ **Invalid staging** = Парсирани, али нису valid
  - Видљиви у staging
  - Могу се исправити или избрисати
  - Могу се аутоматски решити у многим случајевима

- ✅ **Valid** = Парширани и валидни
  - Директно у harvest_records
  - Готови за коришћење
