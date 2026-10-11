import React, { useState, useEffect } from 'react';

// Sidebar Right Component
function SidebarRight() {
  const [isOpen, setIsOpen] = useState(false);
  const [isInschrijvingOpen, setIsInschrijvingOpen] = useState(false);
  const [futureActivities, setFutureActivities] = useState([]);
  const [showInschrijvingButton, setShowInschrijvingButton] = useState(false);
  const [inschrijvingActivity, setInschrijvingActivity] = useState(null);
  const [loading, setLoading] = useState(true);
  // new: track which image is selected for the lightbox
  const [selectedImage, setSelectedImage] = useState(null);
  const [selectedImageAlt, setSelectedImageAlt] = useState('');

  useEffect(() => {
    fetch(`${process.env.PUBLIC_URL}/php/calendar.php`)
      .then(response => response.json())
      .then(data => {
        // calendar.php returns an array directly
        if (Array.isArray(data) && data.length > 0) {
          console.log('All activities:', data);

          // Filter alleen toekomstige activiteiten die een foto hebben
          const futureActivitiesWithPhoto = data.filter(act =>
            (act.status === 'future' || act.status === 'today') && act.photoFilename
          );

          setFutureActivities(futureActivitiesWithPhoto);

          // Check of er een Smakelijk Wandelen activiteit is met inschrijving = true
          const smakelijkWandelenWithInschrijving = data.find(act => {
            const name = (act.name || '').toLowerCase();
            const hasSmakelijk = name.includes('smakelijk');
            const hasWandelen = name.includes('wandelen');
            const hasInschrijving = (Number(act.inschrijving) === 1);
            return hasSmakelijk && hasWandelen && hasInschrijving;
          });

          if (smakelijkWandelenWithInschrijving) {
            setShowInschrijvingButton(true);
            setInschrijvingActivity(smakelijkWandelenWithInschrijving);
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
  };

  if (loading) {
    return (
      <aside className="sidebar sidebar-right">
        <p style={{ padding: '20px', textAlign: 'center' }}>Laden...</p>
      </aside>
    );
  }

  const inschrijvingImagePath = inschrijvingActivity ? getImagePath(inschrijvingActivity) : null;
  const inscriptionActivityIsUpcoming = futureActivities.some(activity =>
    (activity.id && inschrijvingActivity && activity.id === inschrijvingActivity.id) ||
    (activity.photoFilename && inschrijvingActivity && activity.photoFilename === inschrijvingActivity.photoFilename)
  );

  // small helper to open lightbox for a specific image
  const openImage = (path, alt) => {
    setSelectedImage(path);
    setSelectedImageAlt(alt || '');
    setIsOpen(true);
  };

  // image styling: show smaller thumbnails in sidebar
  const imgStyle = {
    width: '100%',
    maxWidth: '220px',
    height: 'auto',
    display: 'block',
    cursor: 'pointer',
    borderRadius: '6px'
  };

  // button style: ensure visible and full width
  const btnStyle = {
    width: '100%',
    padding: '10px 12px',
    background: '#ebe24c',
    color: '#2c3e50',
    border: 'none',
    borderRadius: '5px',
    fontSize: '15px',
    fontWeight: '600',
    cursor: 'pointer',
    marginTop: '10px',
    boxShadow: '0 1px 3px rgba(0,0,0,0.08)'
  };

  return (
    <>
      <aside className="sidebar sidebar-right" style={{ padding: '12px' }}>
        {futureActivities.map((activity) => {
          const imagePath = getImagePath(activity);
          return imagePath && (
            <img
              key={activity.id || activity.photoFilename}
              src={imagePath}
              alt={activity.name}
              onClick={() => openImage(imagePath, activity.name)}
              style={imgStyle}
              onError={(e) => {
                e.target.style.display = 'none';
                const parent = e.target.parentElement;
                if (parent) parent.insertAdjacentHTML('beforeend', `<p style="padding: 12px; text-align: center;">${activity.name}</p>`);
              }}
            />
          );
        })}

        {/* Toon ook de affiche voor Smakelijk Wandelen wanneer inschrijving actief is.
            Als die activiteit al in de lijst staat, toon de affiche niet dubbel. */}
        {inschrijvingActivity && inschrijvingImagePath && !inscriptionActivityIsUpcoming && (
          <img
            src={inschrijvingImagePath}
            alt={inschrijvingActivity.name}
            onClick={() => openImage(inschrijvingImagePath, inschrijvingActivity.name)}
            style={{ ...imgStyle, marginTop: '10px' }}
            onError={(e) => {
              e.target.style.display = 'none';
              const parent = e.target.parentElement;
              if (parent) parent.insertAdjacentHTML('beforeend', `<p style="padding: 12px; text-align: center;">${inschrijvingActivity.name}</p>`);
            }}
          />
        )}

        {/* Inschrijvingsknop: altijd zichtbaar wanneer inschrijving actief */}
        {showInschrijvingButton && inschrijvingActivity && (
          <button
            className="btn-inschrijven"
            onClick={() => setIsInschrijvingOpen(true)}
            style={btnStyle}
          >
            Inschrijving {inschrijvingActivity.name} — {new Date(inschrijvingActivity.date).toLocaleDateString('nl-BE', { day: 'numeric', month: 'long', year: 'numeric' })}
          </button>
        )}
      </aside>

      {/* Lightbox voor foto; show selectedImage */}
      {isOpen && selectedImage && (
        <div className="lightbox-overlay" onClick={() => setIsOpen(false)}>
          <div className="lightbox-content" onClick={(e) => e.stopPropagation()}>
            <button className="lightbox-close" onClick={() => setIsOpen(false)}>×</button>
            <img
              src={selectedImage}
              alt={selectedImageAlt}
              style={{ maxWidth: '95vw', maxHeight: '85vh', display: 'block' }}
            />
            <p style={{ color: 'white', marginTop: '10px', textAlign: 'center' }}>{selectedImageAlt}</p>
          </div>
        </div>
      )}

      {/* Modal voor inschrijving Smakelijk Wandelen */}
      {isInschrijvingOpen && (
        <div className="lightbox-overlay" onClick={() => setIsInschrijvingOpen(false)}>
          <div className="lightbox-content modal-form" onClick={(e) => e.stopPropagation()} style={{
            maxWidth: '900px',
            maxHeight: '90vh',
            overflow: 'auto',
            background: 'white',
            padding: '0',
            borderRadius: '10px'
          }}>
            <button className="lightbox-close" onClick={() => setIsInschrijvingOpen(false)} style={{position: 'absolute', right: 10, top: 10, zIndex: 10}}>×</button>
            {/* Use the single public form so both public site and bestuur/sidebar use the exact same markup and behavior */}
            <iframe src="/form_smakelijk_wandelen.html" title="Inschrijving Smakelijk Wandelen" style={{width: '100%', height: '720px', border: 'none', display: 'block'}} />
          </div>
        </div>
      )}
    </>
  );
}
export default SidebarRight;
