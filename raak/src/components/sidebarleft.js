import React, { useEffect, useState } from 'react';

function isRegistrationOpen(activity, today = new Date()) {
  const todayDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
  const startDate = activity.inschrijvingen_start;
  const endDate = activity.inschrijvingen_end;
  const hasStartDate = startDate && startDate !== '0000-00-00';
  const hasEndDate = endDate && endDate !== '0000-00-00';

  return Boolean(hasStartDate && hasEndDate && todayDate >= startDate && todayDate <= endDate);
}

function getOpenRegistrationActivities(activities) {
  return activities.filter((activity) =>
    Number(activity.inschrijving) === 1 && isRegistrationOpen(activity)
  );
}

function SidebarLeft() {
  const [registrationActivities, setRegistrationActivities] = useState([]);
  const [selectedActivity, setSelectedActivity] = useState(null);

  useEffect(() => {
    fetch('/php/calendar.php')
      .then((response) => {
        if (!response.ok) throw new Error('Kon activiteiten niet laden');
        return response.json();
      })
      .then((activities) => {
        if (Array.isArray(activities)) {
          setRegistrationActivities(getOpenRegistrationActivities(activities));
        }
      })
      .catch((error) => {
        console.error('Kon actieve inschrijvingen niet laden:', error);
      });
  }, []);

  const formUrl = selectedActivity
    ? `/php/calendar_inschrijvingen.php?id_act=${encodeURIComponent(selectedActivity.id)}`
    : '';

  return (
    <>
      <aside className="sidebar sidebar-left">
        <div>
          <a href="https://raakvzw.be/" target="_blank" rel="noreferrer">
            <img src="./images/raak_logo.png" alt="Link naar website van Raak VZW" />
          </a>
          <br />
          <a href="https://www.monavzw.be/" target="_blank" rel="noreferrer">
            <img src="./images/mona_logo.jpg" alt="Link naar website van Mona VZW" />
          </a>
          <br />
          <div style={{ marginTop: '20px' }}>
            <a href="https://www.facebook.com/profile.php?id=61566387080870" target="_blank" rel="noreferrer" title="Raak Achterbos on Facebook" className="social-icon">
              <span className="fa fa-facebook-square" style={{ fontSize: '30px' }}></span>
            </a>
            <a href="https://www.instagram.com/raakachterbos/" target="_blank" rel="noreferrer" title="Raak Achterbos on Instagram" className="social-icon">
              <span className="fa fa-instagram" style={{ fontSize: '30px' }}></span>
            </a>
          </div>
        </div>

        {registrationActivities.length > 0 && (
          <section className="sidebar-registrations" aria-labelledby="sidebar-registrations-title">
            <h3 id="sidebar-registrations-title">Inschrijvingen</h3>
            {registrationActivities.map((activity) => (
              <button
                key={activity.id}
                type="button"
                className="sidebar-registration-button"
                onClick={() => setSelectedActivity(activity)}
              >
                {activity.name}
              </button>
            ))}
          </section>
        )}
      </aside>

      {selectedActivity && (
        <div className="lightbox-overlay" onClick={() => setSelectedActivity(null)}>
          <div className="sidebar-registration-modal" onClick={(event) => event.stopPropagation()}>
            <button
              type="button"
              className="sidebar-registration-close"
              aria-label="Sluiten"
              onClick={() => setSelectedActivity(null)}
            >
              ×
            </button>
            <iframe title={`Inschrijving ${selectedActivity.name}`} src={formUrl} />
          </div>
        </div>
      )}
    </>
  );
}

export default SidebarLeft;
