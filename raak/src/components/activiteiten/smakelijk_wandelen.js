import { useState, useEffect } from 'react';

// Smakelijk wandelen inschrijving Component
// Formulier moet naam, email, aantal personen, aantal vegi, opmerkingen bevatten
// In database moet if, id activiteit, naam, email, aantal personen, aantal vegi, opmerkingen, inschrijfdatum opgeslagen worden
function SmakelijkWandelenPage() {
  const [formData, setFormData] = useState({ naam: '', email: '', aantal_personen: '', aantal_vegi: '', opmerking: '' });
  const [submitted, setSubmitted] = useState(false);
  const [activityInfo, setActivityInfo] = useState(null);
  const currentYear = new Date().getFullYear();

  // Haal activiteit info op bij mount
  useEffect(() => {
    fetch(`${process.env.PUBLIC_URL}/php/calendar.php`)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.data) {
          // Zoek Smakelijk Wandelen activiteit met inschrijving = 1
          const smakelijkActivity = data.data.find(a =>
            a.name.toLowerCase().includes('smakelijk') &&
            a.name.toLowerCase().includes('wandelen') &&
            (a.inschrijving == 1 || a.inschrijving === true || a.inschrijving === "1" || a.inschrijving === "true")
          );
          if (smakelijkActivity) {
            setActivityInfo(smakelijkActivity);
          }
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

      const data = await response.json();
      console.log('Response:', data); // DEBUG

      if (response.ok && data.success) {
        setSubmitted(submittedData); // Bewaar submitted data voor message
        setFormData({ naam: '', email: '', aantal_personen: '', aantal_vegi: '', opmerking: '' });
        setTimeout(() => setSubmitted(false), 10000);
      } else {
        alert('Er ging iets mis: ' + (data.error || 'Onbekende fout') + (data.debug ? '\n\nDebug: ' + data.debug : ''));
      }
    } catch (err) {
      console.error('Fetch error:', err);
      alert('Netwerkfout: ' + err.message);
    }
  };

  // Format datum naar leesbaar Nederlands
  const formatDate = (dateString) => {
    const date = new Date(dateString);
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

  return (
    <div className="contact-section">
      <h1>Inschrijving Smakelijk Wandelen {activityInfo ? new Date(activityInfo.date).getFullYear() : currentYear}</h1>
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
                {' '}We verwachten je op <strong>{formatDate(activityInfo.date)}</strong> te <strong>{activityInfo.place || 'de locatie'}</strong> met <strong>{parseInt(submitted.aantal_personen) + parseInt(submitted.aantal_vegi)}</strong> wandelaars/eters.
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
