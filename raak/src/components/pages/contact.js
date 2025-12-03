import React, { useState } from 'react';
// TODO: testen versturen mail naar raak
// Contact Page Component
function ContactPage() {
  const [formData, setFormData] = useState({ naam: '', email: '', onderwerp: '', bericht: '' });
  const [submitted, setSubmitted] = useState(false);
  const [activeSection, setActiveSection] = useState('bestuur');

  const handleSubmit = async (e) => {
    e.preventDefault();

    try {
      // Send to PHP backend
      const response = await fetch(`${process.env.PUBLIC_URL}/php/contact.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });

      const data = await response.json();
      console.log('Response:', data); // DEBUG

      if (response.ok && data.success) {
        setSubmitted(true);
        setFormData({ naam: '', email: '', onderwerp: '', bericht: '' });
        setTimeout(() => setSubmitted(false), 5000);
      } else {
        alert('Er ging iets mis: ' + (data.error || 'Onbekende fout') + (data.debug ? '\n\nDebug: ' + data.debug : ''));
      }
    } catch (err) {
      console.error('Fetch error:', err);
      alert('Netwerkfout: ' + err.message);
    }
  };

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  return (
    <section className="page">
      <div className="subnav">
        <nav>
          <ul>
            <li><a href="#" className={activeSection === 'bestuur' ? 'active' : ''} onClick={() => setActiveSection('bestuur')}>Ons bestuur</a></li>
            <li><a href="#" className={activeSection === 'contact' ? 'active' : ''} onClick={() => setActiveSection('contact')}>Contacteer ons</a></li>
          </ul>
        </nav>
      </div>

      {activeSection === 'bestuur' && (
        <div className="contact-section">
          <h1>Ons bestuur</h1>
          <p>Hier vindt u informatie over ons bestuursteam.</p>
          <ul>
            <li>Voorzitter: Bram Hannes</li>
            <li>Secretaris: Luc Sannen</li>
            <li>Penningmeester: Alex Van Decraen</li>
          </ul>
          <h1>Wijkmeesters</h1>
          <p>Naast het dagelijks bestuur hebben wij ook nog een heel team van wijkmeesters die het bestuur bijstaan. Dit team zorgt mee voor de organisatie en activiteiten van Raak.</p>
          <p>Hiernaast hebben wij ook de hulp van de vele vrijwilligers die zich inzetten tijdens onze activiteiten. Wil je zelf helpen tijdens een activiteit of wil je je aansluiten tot ons team? Neem dan gerust contact met ons op.</p>
        </div>
      )}

      {activeSection === 'contact' && (
        <div className="contact-section">
          <h1>Contacteer ons</h1>
          <form onSubmit={handleSubmit} className="contact-form">
            <label>Naam:</label>
            <input type="text" name="naam" value={formData.naam} onChange={handleChange} required />

            <label>E-mail:</label>
            <input type="email" name="email" value={formData.email} onChange={handleChange} required />

            <label>Onderwerp</label>
            <input name="onderwerp" value={formData.onderwerp} onChange={handleChange} required />

            <label>Bericht:</label>
            <textarea name="bericht" rows="5" value={formData.bericht} onChange={handleChange} required></textarea>

            <button
              type="submit"
              disabled={!formData.naam || !formData.email || !formData.onderwerp || !formData.bericht}
            >
              Verzenden
            </button>
          </form>
          {submitted && (
            <div className="success-message">
              Bedankt voor je bericht, {formData.naam || 'daar'}! We nemen zo snel mogelijk contact op.
            </div>
          )}
        </div>
      )}
    </section>
  );
}
export default ContactPage;