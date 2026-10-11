// place activities folder on home.js

// declare constants
const fs = require('fs');
const path = require('path');
const activitiesDir = path.join(__dirname, '..', 'public', 'activities');
const outFile = path.join(activitiesDir, 'manifest.json');

// check directory exists
if (!fs.existsSync(activitiesDir)) {
    console.error('Activities directory does not exist:', activitiesDir);
    process.exit(1);
}

// read files
const files = fs.readdirSync(activitiesDir).filter(f => /\.(jpg|jpeg|png|gif|webp|bmp|tiff?)$/i.test(f));

const items = files.map(f => {
    const m = f.match(/^(\d{8})/);
    if (!m) return null;
    const ymd = m[1];
    const year = ymd.slice(0, 4);
    const month = ymd.slice(4, 6);
    const day = ymd.slice(6, 8);
    const date = `${year}-${month}-${day}`; // ISO-like
    // Build label by removing leading date+ underscore and extension
    let label = f.replace(/^\d{8}_?/, '');
    label = label.replace(/\.[^.]+$/, '');
    label = label.replace(/[-_]+/g, ' ').trim();
    return { file: f, date, label };
}).filter(Boolean);

// sort by date asc
items.sort((a,b) => a.date.localeCompare(b.date));

try {
    fs.writeFileSync(outFile, JSON.stringify(items, null, 2), 'utf8');
    console.log('Wrote activities manifest to', outFile, 'items:', items.length);
} catch (err) {
    console.error('Failed to write manifest:', err);
    process.exit(1);
}
