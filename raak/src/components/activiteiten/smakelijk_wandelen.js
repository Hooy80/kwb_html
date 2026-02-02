import { useState, useEffect } from 'react';

// Smakelijk wandelen inschrijving Component
// Formulier moet naam, email, aantal personen, aantal vegi, opmerkingen bevatten
// In database moet if, id activiteit, naam, email, aantal personen, aantal vegi, opmerkingen, inschrijfdatum opgeslagen worden
function SmakelijkWandelenPage() {
  const [formData, setFormData] = useState({ naam: '', email: '', aantal_personen: '', aantal_vegi: '', opmerking: '' });
  const [submitted, setSubmitted] = useState(false);
  const [activityInfo, setActivityInfo] = useState(null);
  const currentYear = new Date().getFullYear();

  // Helper: return a date string with the year replaced by currentYear (yyyy-mm-dd)
  const withCurrentYear = (dateString) => {
    if (!dateString) return dateString;
    const d = new Date(dateString);
    if (!d || isNaN(d.getTime())) return dateString;
    // Keep month and day, but set year to currentYear
    d.setFullYear(currentYear);
    // Return in ISO format (yyyy-mm-dd)
    return d.toISOString().split('T')[0];
  };

  // Haal activiteit info op bij mount
  useEffect(() => {
    fetch(`${process.env.PUBLIC_URL}/php/calendar.php`)
      .then(res => res.json())
      .then(data => {
        // Accept both direct array and { success, data } shape
        const activities = Array.isArray(data) ? data : (data && data.data ? data.data : []);

        // Filter Smakelijk Wandelen met inschrijving actief
        const matches = activities.filter(a => {
          const name = (a?.name || '').toLowerCase();
          const hasName = name.includes('smakelijk') && name.includes('wandelen');
          const hasInschrijving = (a?.inschrijving == 1 || a?.inschrijving === true || String(a?.inschrijving) === '1' || String(a?.inschrijving) === 'true');
          return hasName && hasInschrijving;
        });

        const today = new Date();

        if (matches.length === 0) {
          // Fallback: geen actieve inschrijving gevonden -> toon 10 mei van huidig jaar
          const fallbackDate = new Date(today.getFullYear(), 4, 10); // May 10
          setActivityInfo({ date: fallbackDate.toISOString().split('T')[0], place: '' });
          return;
        }

        // Kies eerste toekomstige activiteit
        let chosen = matches.find(a => {
          const d = a?.date ? new Date(a.date) : null;
          return d && d >= new Date(today.getFullYear(), today.getMonth(), today.getDate());
        });

        // Anders kies activiteit in huidig jaar
        if (!chosen) {
          const currentYear = today.getFullYear();
          chosen = matches.find(a => {
            const d = a?.date ? new Date(a.date) : null;
            return d && d.getFullYear() === currentYear;
          });
        }

        // Anders laatste match
        if (!chosen) chosen = matches[matches.length - 1];

        if (chosen) {
          // Normeer datum: als de DB datum geldig, gebruik die; anders fallback handled above
          const normalized = { ...chosen };
          // ensure date string is ISO yyyy-mm-dd
          if (normalized.date) {
            const d = new Date(normalized.date);
            if (!isNaN(d.getTime())) normalized.date = d.toISOString().split('T')[0];
          }
          setActivityInfo(normalized);
        }
      })
      .catch(err => console.error('Error loading activity:', err));
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();

    // Bewaar formData voor success message (voor reset)
    const submittedData = { ...formData };

    try {
      // Send to PHP backend
      const response = await fetch(`${process.env.PUBLIC_URL}/php/smakelijk_wandelen.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });

      // Try to parse JSON, but guard against non-JSON responses
      let data;
      try {
        data = await response.json();
      } catch (err) {
        data = null;
      }

      console.log('Response:', data); // DEBUG

      if (response.ok && data && data.success) {
        setSubmitted(submittedData); // Bewaar submitted data voor message
        setFormData({ naam: '', email: '', aantal_personen: '', aantal_vegi: '', opmerking: '' });
        setTimeout(() => setSubmitted(false), 10000);
      } else {
        alert('Er ging iets mis: ' + ((data && data.error) || 'Onbekende fout') + ((data && data.debug) ? '\n\nDebug: ' + data.debug : ''));
      }
    } catch (err) {
      console.error('Fetch error:', err);
      alert('Netwerkfout: ' + err.message);
    }
  };

  // Format datum naar leesbaar Nederlands
  const formatDate = (dateString) => {
    const date = dateString ? new Date(dateString) : null;
    if (!date || isNaN(date.getTime())) return 'datum onbekend';
    return date.toLocaleDateString('nl-BE', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  };

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const totalPeople = (submitted && (parseInt(submitted.aantal_personen || '0', 10) + parseInt(submitted.aantal_vegi || '0', 10))) || 0;

  return (
    <div className="contact-section">
      <h1>Inschrijving Smakelijk Wandelen {currentYear}</h1>
      {activityInfo && (
        <p style={{ textAlign: 'center', marginBottom: '20px', fontSize: '18px', fontWeight: 'bold' }}>
          {formatDate(activityInfo.date)} - {activityInfo.place}
        </p>
      )}
      <form onSubmit={handleSubmit} className="contact-form">
        <label>Naam:</label>
        <input type="text" name="naam" value={formData.naam} onChange={handleChange} required />

        <label>E-mail:</label>
        <input type="email" name="email" value={formData.email} onChange={handleChange} required />

        <label>Aantal personen (niet vegi)</label>
        <input type="number" name="aantal_personen" value={formData.aantal_personen} onChange={handleChange} required />

        <label>Aantal personen vegi</label>
        <input type="number" name="aantal_vegi" value={formData.aantal_vegi} onChange={handleChange} required />

        <label>Speciale diëten/allergieën?</label>
        <input name="opmerking" value={formData.opmerking} onChange={handleChange} required />

        <button
          type="submit"
          disabled={!formData.naam || !formData.email || !formData.aantal_personen || !formData.aantal_vegi}
        >
          Verzenden
        </button>
      </form>
      {submitted && (
        <div className="success-message">
          <p>
            Bedankt voor je inschrijving, {submitted.naam}!
            {activityInfo && (
              <>
                {' '}We verwachten je op <strong>{formatDate(activityInfo.date)}</strong> te <strong>{activityInfo.place || 'de locatie'}</strong> met <strong>{totalPeople}</strong> wandelaars/eters.
              </>
            )}
          </p>
          {submitted.opmerking && (
            <p style={{ marginTop: '10px', fontStyle: 'italic' }}>
              Jouw opmerking: "{submitted.opmerking}"
            </p>
          )}
          <p style={{ marginTop: '10px' }}>Tot dan, scouts - gidsen, Ferm en Raak.</p>
        </div>
      )}
    </div>
  )
}
export default SmakelijkWandelenPage
