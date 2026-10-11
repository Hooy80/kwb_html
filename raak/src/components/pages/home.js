// Home Page Component
import React, { useEffect, useState } from "react";

function HomePage() {
  const [activities, setActivities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const manifestUrl = `${process.env.PUBLIC_URL}/activities/manifest.json`;
    const apiEndpoint = '/php/activities_list.php';

    async function loadFromManifest() {
      try {
        const res = await fetch(manifestUrl);
        if (!res.ok) throw new Error('manifest not found');
        const data = await res.json();
        return Array.isArray(data) ? data : [];
      } catch (err) {
        return null; // signal to try API endpoint
      }
    }

    async function loadActivities() {
      try {
        let data = await loadFromManifest();
        if (data === null) {
          const res = await fetch(apiEndpoint);
          if (!res.ok) throw new Error('endpoint not found: ' + res.status);
          data = await res.json();
        }

        const list = Array.isArray(data) ? data : (data && data.data ? data.data : []);

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const future = list
          .filter((item) => {
            if (!item) return false;
            const dateStr = item.date || item.Date || null;
            if (!dateStr) return false;
            const parts = String(dateStr).split('-').map(Number);
            if (parts.length !== 3) return false;
            const [y, m, d] = parts;
            const dt = new Date(y, m - 1, d);
            dt.setHours(0, 0, 0, 0);
            return dt >= today;
          })
          .sort((a, b) => String(a.date).localeCompare(String(b.date)));

        setActivities(future);
      } catch (err) {
        console.error('Error loading activities:', err);
        setError(err.message || String(err));
        setActivities([]);
      } finally {
        setLoading(false);
      }
    }

    loadActivities();
  }, []);

  // helper to get filename (supports different backends)
  function filenameOf(item) {
    if (!item) return null;
    return item.file || item.photoFilename || item.photo || null;
  }

  return (
    <section className="page">
      <img
        src={`${process.env.PUBLIC_URL}/images/raak_001.jpg`}
        alt="RAAK"
        style={{ maxWidth: '100%', marginBottom: '20px' }}
      />

      <h1>Welkom bij RAAK Achterbos</h1>
      <p>Een warme gemeenschap in beweging</p>

      <h2>Binnenkort bij RAAK</h2>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', alignItems: 'flex-start' }}>
        {loading ? (
          <p>Loading activities…</p>
        ) : error ? (
          <p style={{ color: 'red' }}>Fout bij laden activiteiten: {error}</p>
        ) : activities.length === 0 ? (
          <p>Geen aankomende activiteiten.</p>
        ) : (
          activities.map((item, idx) => {
            const fname = filenameOf(item);
            if (!fname) return null;
            // show slightly smaller images for sidebar-like display
            return (
              <div key={fname + '-' + idx} style={{ textAlign: 'center' }}>
                <img
                  src={`${process.env.PUBLIC_URL}/activities/${fname}`}
                  alt={item.label || item.name || fname}
                  title={`${item.label || item.name || ''} — ${item.date || ''}`}
                  style={{ width: '180px', maxWidth: '22vw', margin: '5px', cursor: 'pointer', height: 'auto', objectFit: 'cover' }}
                  onClick={() => window.open(`${process.env.PUBLIC_URL}/activities/${fname}`, '_blank')}
                />
                <div style={{ fontSize: '0.9rem' }}>{item.label || item.name || ''}</div>
                <div style={{ fontSize: '0.9rem', color: '#666' }}>{item.date}</div>
              </div>
            );
          })
        )}
      </div>
    </section>
  );
}

export default HomePage;

// cd /home/wim/Documents/raak_html/raak && ./deploy.sh