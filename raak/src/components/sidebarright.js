import React, { useState, useEffect } from 'react';
import SmakelijkWandelenPage from './activiteiten/smakelijk_wandelen';

// Sidebar Right Component
function SidebarRight() {
  const [isOpen, setIsOpen] = useState(false);
  const [isInschrijvingOpen, setIsInschrijvingOpen] = useState(false);
  const [nextActivity, setNextActivity] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Haal de eerstvolgende activiteit op uit de API
    fetch(`${process.env.PUBLIC_URL}/php/calendar.php`)
      .then(response => response.json())
      .then(data => {
        console.log('API response:', data); // DEBUG
        if (data.success && data.data.length > 0) {
          // Filter alleen toekomstige activiteiten die een foto hebben
          const futureActivitiesWithPhoto = data.data.filter(act =>
            (act.status === 'future' || act.status === 'today') && act.photoFilename
          );
          console.log('Future activities with photo:', futureActivitiesWithPhoto); // DEBUG
          if (futureActivitiesWithPhoto.length > 0) {
            setNextActivity(futureActivitiesWithPhoto[0]); // Eerste = dichtsbijzijnde met foto
            console.log('Selected activity:', futureActivitiesWithPhoto[0]); // DEBUG
          }
        }
        setLoading(false);
      })
      .catch(err => {
        console.error('Kon activiteit niet laden:', err);
        setLoading(false);
      });
  }, []);

  // Genereer bestandsnaam: gebruik photoFilename uit API
  const getImagePath = (activity) => {
    if (!activity || !activity.photoFilename) return null;
    return `${process.env.PUBLIC_URL}/activities/${activity.photoFilename}`;
  }; if (loading) {
    return (
      <aside className="sidebar sidebar-right">
        <p style={{ padding: '20px', textAlign: 'center' }}>Laden...</p>
      </aside>
    );
  }

  if (!nextActivity) {
    return (
      <aside className="sidebar sidebar-right">
        {/* Geen activiteiten met foto - toon niets */}
      </aside>
    );
  }

  const imagePath = getImagePath(nextActivity);

  // Check of activiteit "Smakelijk Wandelen" is
  const isSmakelijkWandelen = nextActivity &&
    nextActivity.name.toLowerCase().includes('smakelijk') &&
    nextActivity.name.toLowerCase().includes('wandelen');

  return (
    <>
      <aside className="sidebar sidebar-right">
        <img
          src={imagePath}
          alt={nextActivity.name}
          onClick={() => setIsOpen(true)}
          style={{ cursor: 'pointer' }}
          onError={(e) => {
            // Fallback als foto niet bestaat
            e.target.style.display = 'none';
            e.target.parentElement.innerHTML = `<p style="padding: 20px; text-align: center;">${nextActivity.name}</p>`;
          }}
        />

        {/* Inschrijven knop voor Smakelijk Wandelen */}
        {isSmakelijkWandelen && (
          <button
            className="btn-inschrijven"
            onClick={() => setIsInschrijvingOpen(true)}
            style={{
              width: '100%',
              padding: '12px',
              marginTop: '10px',
              background: '#ebe24c',
              color: '#2c3e50',
              border: 'none',
              borderRadius: '5px',
              fontSize: '16px',
              fontWeight: 'bold',
              cursor: 'pointer',
              transition: 'opacity 0.3s'
            }}
            onMouseOver={(e) => e.target.style.opacity = '0.9'}
            onMouseOut={(e) => e.target.style.opacity = '1'}
          >
            Inschrijven
          </button>
        )}
      </aside>

      {/* Lightbox voor foto */}
      {isOpen && (
        <div className="lightbox-overlay" onClick={() => setIsOpen(false)}>
          <div className="lightbox-content" onClick={(e) => e.stopPropagation()}>
            <button className="lightbox-close" onClick={() => setIsOpen(false)}>×</button>
            <img
              src={imagePath}
              alt={nextActivity.name}
            />
            <p style={{ color: 'white', marginTop: '10px', textAlign: 'center' }}>{nextActivity.name}</p>
          </div>
        </div>
      )}

      {/* Modal voor inschrijving Smakelijk Wandelen */}
      {isInschrijvingOpen && (
        <div className="lightbox-overlay" onClick={() => setIsInschrijvingOpen(false)}>
          <div className="lightbox-content modal-form" onClick={(e) => e.stopPropagation()} style={{
            maxWidth: '600px',
            maxHeight: '90vh',
            overflow: 'auto',
            background: 'white',
            padding: '20px',
            borderRadius: '10px'
          }}>
            <button className="lightbox-close" onClick={() => setIsInschrijvingOpen(false)}>×</button>
            <SmakelijkWandelenPage />
          </div>
        </div>
      )}
    </>
  );
}
export default SidebarRight;