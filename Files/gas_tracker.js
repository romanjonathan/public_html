// Google Apps Script — Health Tracker API
//
// Sheet structure:
//   A: Date  |  B: Weight (lbs)  |  C: Screen Time (H:mm, text)  |  D: Screen Time (decimal hrs)
//
// Setup:
// 1. Go to script.google.com → New project → paste this file
// 2. Deploy → New deployment → Web app
//      Execute as: Me
//      Who has access: Anyone
// 3. Paste the deployment URL into health_tracker.php

const SHEET_ID = '1Rzio_BmpHrqCwWwh2IfbWPEKOrqC-NHmebITni7D3ks';

function getSheet() {
  return SpreadsheetApp.openById(SHEET_ID).getSheets()[0];
}

function doGet(e) {
  const n    = parseInt(e.parameter.n) || 16;
  const data = getSheet().getDataRange().getValues();
  const rows = data.slice(1)
    .filter(row => row[0])
    .sort((a, b) => new Date(a[0]) - new Date(b[0]))
    .slice(-n);

  const result = rows.map(row => ({
    date:       Utilities.formatDate(new Date(row[0]), Session.getScriptTimeZone(), 'yyyy-MM-dd'),
    weight:     row[1],
    screentime: (row[3] !== '' && row[3] != null) ? row[3] : row[2]  // col D if present, else col C (old entries)
  }));

  return ContentService
    .createTextOutput(JSON.stringify(result))
    .setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  const p       = JSON.parse(e.postData.contents);
  const decimal = parseFloat(p.screentime);
  const hrs     = Math.floor(decimal);
  const mins    = Math.round((decimal - hrs) * 60);
  const hhmm    = hrs + ':' + String(mins).padStart(2, '0');

  const sheet  = getSheet();
  const newRow = sheet.getLastRow() + 1;

  sheet.getRange(newRow, 1).setValue(new Date(p.date));
  sheet.getRange(newRow, 2).setValue(parseFloat(p.weight));

  // Force plain text so Sheets doesn't auto-convert "2:30" to a time value
  const hhmmCell = sheet.getRange(newRow, 3);
  hhmmCell.setNumberFormat('@');
  hhmmCell.setValue(hhmm);

  sheet.getRange(newRow, 4).setValue(decimal);

  return ContentService
    .createTextOutput(JSON.stringify({ success: true }))
    .setMimeType(ContentService.MimeType.JSON);
}
