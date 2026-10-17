import React, { useState, useEffect } from 'react';

// Sidebar Right Component
function SidebarRight() {
  const [isOpen, setIsOpen] = useState(false);
  const [futureActivities, setFutureActivities] = useState([]);
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

    </>
  );
}
export default SidebarRight;
