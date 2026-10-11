const fs = require('fs');
const path = require('path');

const activitiesDir = path.join(__dirname, '..', 'raak', 'public', 'activities');
const outFile = path.join(activitiesDir, 'manifest.json');

if (!fs.existsSync(activitiesDir)) {
  console.error('activities directory not found:', activitiesDir);
  process.exit(1);
}

const files = fs.readdirSync(activitiesDir).filter(f => /\.(jpe?g|png|gif|bmp|tiff?)$/i.test(f));

const items = files.map(f => {
  const m = f.match(/^(\d{8})/);
  if (!m) return null;
  const ymd = m[1];
  const year = ymd.slice(0,4);
  const month = ymd.slice(4,6);
  const day = ymd.slice(6,8);
  const date = `${year}-${month}-${day}`;
  const label = f.replace(/^\d+_?/, '').replace(/\.(jpe?g|png|gif|bmp|tiff?)$/i, '').replace(/[-_]+/g, ' ').trim();
  return { file: f, date, label };
}).filter(Boolean);

items.sort((a,b) => a.date.localeCompare(b.date));

fs.writeFileSync(outFile, JSON.stringify(items, null, 2), 'utf8');
console.log('Wrote', items.length, 'items to', outFile);

