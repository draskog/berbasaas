<?php

namespace App\Enums;

enum ImportType: string
{
    case ScaleCsv = 'scale_csv';
    case ManualCsv = 'manual_csv';
}
