#!/usr/bin/env node
/*
  Sanity check for activities manifest and corresponding files.
  - Ensures each manifest entry has file + date and file exists
  - Ensures date is parseable YYYY-MM-DD
  - Prints which entries are upcoming compared to today

  Usage:
    node scripts/check_upcoming_manifest.js
*/

const fs = require('fs');
const path = require('path');

const activitiesDir = path.join(__dirname, '..', 'public', 'activities');
const manifestPath = path.join(activitiesDir, 'manifest.json');

function parseISODate(dateStr) {
  if (!dateStr) return null;
  const m = String(dateStr).match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return null;
  const y = Number(m[1]);
  const mo = Number(m[2]);
  const d = Number(m[3]);
  const dt = new Date(y, mo - 1, d);
  dt.setHours(0, 0, 0, 0);
  // validate rollover
  if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return null;
  return dt;
}

function main() {
  if (!fs.existsSync(manifestPath)) {
    console.error('Manifest not found:', manifestPath);
    process.exit(2);
  }
  const raw = fs.readFileSync(manifestPath, 'utf8');
  let items;
  try {
    items = JSON.parse(raw);
  } catch (e) {
    console.error('Manifest is not valid JSON:', e.message);
    process.exit(2);
  }
  if (!Array.isArray(items)) {
    console.error('Manifest must be a JSON array.');
    process.exit(2);
  }

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  let errors = 0;
  const upcoming = [];

  for (const [idx, it] of items.entries()) {
    const prefix = `#${idx}`;
    if (!it || typeof it !== 'object') {
      console.error(prefix, 'Invalid item (not an object)');
      errors++;
      continue;
    }
    if (!it.file) {
      console.error(prefix, 'Missing "file" field');
      errors++;
    } else {
      const fpath = path.join(activitiesDir, it.file);
      if (!fs.existsSync(fpath)) {
        console.error(prefix, 'File not found on disk:', it.file);
        errors++;
      }
    }

    const dt = parseISODate(it.date);
    if (!dt) {
      console.error(prefix, 'Bad or missing "date" (expected YYYY-MM-DD):', it.date);
      errors++;
    } else if (dt >= today) {
      upcoming.push({ file: it.file, date: it.date, label: it.label || '' });
    }
  }

  upcoming.sort((a, b) => String(a.date).localeCompare(String(b.date)));

  console.log('Today:', today.toISOString().slice(0, 10));
  console.log('Upcoming entries:', upcoming.length);
  for (const u of upcoming) {
    console.log(`  - ${u.date}  ${u.file}${u.label ? '  (' + u.label + ')' : ''}`);
  }

  if (errors) {
    console.error(`\nFound ${errors} issue(s).`);
    process.exit(1);
  }
}

main();
