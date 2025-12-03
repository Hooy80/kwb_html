import { useState, useEffect } from 'react';

// Media Page Component
function MediaPage() {
  const [activeSection, setActiveSection] = useState('fotos');
  const [folders, setFolders] = useState([]);
  const [pdfFolders, setPdfFolders] = useState([]);
  const [selectedFolder, setSelectedFolder] = useState(null);
  const [lightboxImage, setLightboxImage] = useState(null);
  const [lightboxIndex, setLightboxIndex] = useState(0);

  useEffect(() => {
    if (activeSection === 'fotos') {
      fetch('/php/pictures.php')
        .then(res => res.json())
        .then(data => {
          console.log('Loaded folders:', data);
          // Sort folders descending by name
          const sortedFolders = [...data].sort((a, b) => b.name.localeCompare(a.name));
          setFolders(sortedFolders);
        })
        .catch(err => console.error('Error loading folders:', err));
    } else if (activeSection === 'folders') {
      fetch('/php/folders.php')
        .then(res => res.json())
        .then(data => {
          console.log('Loaded PDF folders:', data);
          setPdfFolders(data);
        })
        .catch(err => console.error('Error loading PDF folders:', err));
    }
  }, [activeSection]);

  const openFolder = (folder) => {
    console.log('Opening folder:', folder);
    console.log('Photos in folder:', folder.photos);
    setSelectedFolder(folder);
  };

  const closeFolder = () => {
    setSelectedFolder(null);
  };

  const openLightbox = (photo, index) => {
    setLightboxImage(photo);
    setLightboxIndex(index);
  };

  const closeLightbox = () => {
    setLightboxImage(null);
  };

  const nextPhoto = () => {
    if (selectedFolder && lightboxIndex < selectedFolder.photos.length - 1) {
      const newIndex = lightboxIndex + 1;
      setLightboxIndex(newIndex);
      setLightboxImage(selectedFolder.photos[newIndex]);
    }
  };

  const prevPhoto = () => {
    if (selectedFolder && lightboxIndex > 0) {
      const newIndex = lightboxIndex - 1;
      setLightboxIndex(newIndex);
      setLightboxImage(selectedFolder.photos[newIndex]);
    }
  };

  const getImagePath = (folderName, photoName) => {
    return `${process.env.PUBLIC_URL}/pictures/${folderName}/${photoName}`;
  };

  const getPdfPath = (filename) => {
    return `${process.env.PUBLIC_URL}/folders/${filename}`;
  };

  const openPdf = (filename) => {
    window.open(getPdfPath(filename), '_blank');
  };

  return (
    <section className="page">
      <div className="subnav">
        <nav>
          <ul>
            <li><a href="#" className={activeSection === 'folders' ? 'active' : ''} onClick={(e) => { e.preventDefault(); setActiveSection('folders'); }}>Folders</a></li>
            <li><a href="#" className={activeSection === 'fotos' ? 'active' : ''} onClick={(e) => { e.preventDefault(); setActiveSection('fotos'); setSelectedFolder(null); }}>Foto's</a></li>
          </ul>
        </nav>
      </div>

      {activeSection === 'folders' && (
        <div className="media-section">
          <h1>Folders</h1>
          <div className="photo-folders">
            {pdfFolders.map((folder) => (
              <div key={folder.file} className="folder-card" onClick={() => openPdf(folder.file)}>
                <div className="folder-thumbnail">
                  <img
                    src={`${process.env.PUBLIC_URL}/images/pdf.png`}
                    alt="PDF"
                    onError={(e) => e.target.style.display = 'none'}
                  />
                </div>
                <div className="folder-info">
                  <h3>{folder.displayName}</h3>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {activeSection === 'fotos' && !selectedFolder && (
        <div className="media-section">
          <h1>Foto's</h1>
          <div className="photo-folders">
            {folders.map((folder) => (
              <div key={folder.name} className="folder-card" onClick={() => openFolder(folder)}>
                <div className="folder-thumbnail">
                  <img
                    src={getImagePath(folder.name, folder.firstPhoto)}
                    alt={folder.name}
                    onError={(e) => e.target.style.display = 'none'}
                  />
                </div>
                <div className="folder-info">
                  <h3>{folder.name}</h3>
                  <p>{folder.photoCount} foto's</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {activeSection === 'fotos' && selectedFolder && (
        <div className="media-section">
          <button className="back-button" onClick={closeFolder}>← Terug naar overzicht</button>
          <h1>{selectedFolder.name}</h1>
          <p>{selectedFolder.photoCount} foto's</p>
          <div className="photo-grid">
            {selectedFolder.photos.map((photo, index) => (
              <div key={photo} className="photo-thumbnail" onClick={() => openLightbox(photo, index)}>
                <img
                  src={getImagePath(selectedFolder.name, photo)}
                  alt={photo}
                  onError={(e) => e.target.style.display = 'none'}
                />
              </div>
            ))}
          </div>
        </div>
      )}

      {lightboxImage && selectedFolder && (
        <div className="lightbox-overlay" onClick={closeLightbox}>
          <div className="lightbox-content" onClick={(e) => e.stopPropagation()}>
            <button className="lightbox-close" onClick={closeLightbox}>×</button>
            <button
              className="lightbox-prev"
              onClick={prevPhoto}
              disabled={lightboxIndex === 0}
            >
              ‹
            </button>
            <img
              src={getImagePath(selectedFolder.name, lightboxImage)}
              alt={lightboxImage}
            />
            <button
              className="lightbox-next"
              onClick={nextPhoto}
              disabled={lightboxIndex === selectedFolder.photos.length - 1}
            >
              ›
            </button>
            <div className="lightbox-counter">
              {lightboxIndex + 1} / {selectedFolder.photoCount}
            </div>
          </div>
        </div>
      )}
    </section>
  );
}
export default MediaPage;