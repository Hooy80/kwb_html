// Test script - plak dit in de browser console van de bestuur pagina

fetch('/php/debug_post.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    action: 'send',
    onderwerp: 'TEST',
    bericht: 'Test bericht',
    emails: ['test@example.com']
  })
})
  .then(response => response.text())
  .then(text => {
    console.log('Raw response:', text);
    try {
      const data = JSON.parse(text);
      console.log('Parsed data:', data);
    } catch (e) {
      console.error('Parse error:', e);
    }
  })
  .catch(error => console.error('Fetch error:', error));
