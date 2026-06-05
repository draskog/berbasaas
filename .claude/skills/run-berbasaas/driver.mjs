#!/usr/bin/env node
/**
 * Berbasaas Driver
 *
 * Programmatic control of the Laravel Sail application using Playwright.
 * Commands: launch, visit <url>, click <selector>, fill <selector> <text>,
 *           screenshot <filename>, wait <ms>, eval <code>, quit
 */

import { chromium } from 'playwright';
import * as fs from 'fs';
import * as path from 'path';
import * as readline from 'readline';

let browser = null;
let page = null;
const screenshotDir = './.claude/skills/run-berbasaas/screenshots';

// Ensure screenshot directory exists
if (!fs.existsSync(screenshotDir)) {
  fs.mkdirSync(screenshotDir, { recursive: true });
}

async function launch() {
  if (browser) {
    console.log('Browser already running');
    return;
  }

  browser = await chromium.launch({
    headless: true,
    args: ['--disable-gpu', '--no-sandbox']
  });

  page = await browser.newPage({
    // Simulate a desktop viewport
    viewport: { width: 1280, height: 720 }
  });

  // Listen for console messages
  page.on('console', msg => console.log(`[BROWSER] ${msg.text()}`));
  page.on('pageerror', err => console.error(`[ERROR] ${err.message}`));

  console.log('Browser launched');
}

async function visit(url) {
  if (!page) {
    console.error('Browser not launched. Call launch first.');
    return;
  }

  // Resolve localhost URLs through Docker
  if (url.includes('localhost') || url.includes('127.0.0.1')) {
    url = url.replace(/localhost|127\.0\.0\.1/, 'berbasaas.test');
  }

  // Ensure protocol
  if (!url.startsWith('http')) {
    url = `http://${url}`;
  }

  try {
    await page.goto(url, { waitUntil: 'networkidle' });
    console.log(`Navigated to ${url}`);
  } catch (err) {
    console.error(`Failed to navigate: ${err.message}`);
  }
}

async function click(selector) {
  if (!page) {
    console.error('Browser not launched');
    return;
  }

  try {
    await page.click(selector);
    console.log(`Clicked ${selector}`);
  } catch (err) {
    console.error(`Failed to click: ${err.message}`);
  }
}

async function fill(selector, text) {
  if (!page) {
    console.error('Browser not launched');
    return;
  }

  try {
    await page.fill(selector, text);
    console.log(`Filled ${selector} with "${text}"`);
  } catch (err) {
    console.error(`Failed to fill: ${err.message}`);
  }
}

async function screenshot(filename) {
  if (!page) {
    console.error('Browser not launched');
    return;
  }

  try {
    const filepath = path.join(screenshotDir, filename || 'screenshot.png');
    await page.screenshot({ path: filepath, fullPage: false });
    console.log(`Screenshot saved to ${filepath}`);
  } catch (err) {
    console.error(`Failed to take screenshot: ${err.message}`);
  }
}

async function wait(ms) {
  await new Promise(resolve => setTimeout(resolve, parseInt(ms) || 1000));
  console.log(`Waited ${ms}ms`);
}

async function evaluate(code) {
  if (!page) {
    console.error('Browser not launched');
    return;
  }

  try {
    const result = await page.evaluate(code);
    console.log(`Result: ${JSON.stringify(result)}`);
  } catch (err) {
    console.error(`Evaluation failed: ${err.message}`);
  }
}

async function quit() {
  if (browser) {
    await browser.close();
    browser = null;
    page = null;
    console.log('Browser closed');
  }
  process.exit(0);
}

async function processCommand(command) {
  const [cmd, ...args] = command.trim().split(/\s+/);

  switch (cmd.toLowerCase()) {
    case 'launch':
      await launch();
      break;
    case 'visit':
      await visit(args.join(' '));
      break;
    case 'click':
      await click(args.join(' '));
      break;
    case 'fill':
      {
        const selector = args[0];
        const text = args.slice(1).join(' ');
        await fill(selector, text);
      }
      break;
    case 'screenshot':
      await screenshot(args.join(' '));
      break;
    case 'wait':
      await wait(args[0]);
      break;
    case 'eval':
      await evaluate(args.join(' '));
      break;
    case 'quit':
    case 'exit':
      await quit();
      break;
    case 'help':
      console.log(`
Available commands:
  launch                      - Launch browser
  visit <url>                 - Navigate to URL
  click <selector>            - Click element
  fill <selector> <text>      - Fill input field
  screenshot [filename]       - Take screenshot
  wait <ms>                   - Wait in milliseconds
  eval <code>                 - Execute JavaScript
  quit                        - Exit driver
  help                        - Show this help
      `);
      break;
    default:
      if (cmd) {
        console.error(`Unknown command: ${cmd}`);
      }
  }
}

async function startRepl() {
  console.log('Berbasaas Driver REPL');
  console.log('Type "help" for commands, "quit" to exit\n');

  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
    prompt: '> '
  });

  rl.prompt();

  rl.on('line', async (command) => {
    if (command.trim()) {
      await processCommand(command);
    }
    rl.prompt();
  });

  rl.on('close', async () => {
    if (browser) {
      await browser.close();
    }
    process.exit(0);
  });
}

// If arguments provided, execute as commands
if (process.argv.length > 2) {
  const commands = process.argv.slice(2).join(' ');
  (async () => {
    await processCommand(commands);
  })();
} else {
  // Start REPL
  startRepl();
}