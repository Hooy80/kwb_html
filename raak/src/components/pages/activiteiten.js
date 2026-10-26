import React, { useState, useEffect } from 'react';

// Activities Page Component
function ActivitiesPage() {
  const [openActivity, setOpenActivity] = useState(null);
  const [activities, setActivities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    // Fetch activiteiten van PHP API in raak/php/
    fetch(`${process.env.PUBLIC_URL}/php/calendar.php`)
      .then(response => response.json())
      .then(data => {
        // calendar.php geeft direct een array terug
        if (Array.isArray(data)) {
          // Filter: toekomstige, vandaag, en voorbije activiteiten van laatste maand
          const oneMonthAgo = new Date();
          oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);

          const relevantActivities = data.filter(a => {
            if (a.status === 'future' || a.status === 'today') return true;
            if (a.status === 'past') {
              const activityDate = new Date(a.date);
              return activityDate >= oneMonthAgo;
            }
            return false;
          });

          setActivities(relevantActivities);
        } else if (data.error) {
          setError(data.error);
        } else {
          setError('Onverwacht response formaat');
        }
        setLoading(false);
      })
      .catch(err => {
        setError('Kon activiteiten niet laden: ' + err.message);
        setLoading(false);
      });
  }, []);

  const toggleActivity = (id) => {
    setOpenActivity(openActivity === id ? null : id);
  };

  const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    const options = { day: 'numeric', month: 'long', year: 'numeric' };
    return date.toLocaleDateString('nl-BE', options);
  };

  const formatTime = (timeStr) => {
    if (!timeStr) return '';
    return timeStr.substring(0, 5);
  };

  if (loading) {
    return (
      <section className="page">
        <h1>Activiteiten</h1>
        <p>Activiteiten laden...</p>
      </section>
    );
  }

  if (error) {
    return (
      <section className="page">
        <h1>Activiteiten</h1>
        <p style={{ color: 'red' }}>Fout: {error}</p>
      </section>
    );
  }

  return (
    <section className="page">
      <h1>Activiteiten</h1>
      {activities.length === 0 ? (
        <p>Geen activiteiten gevonden.</p>
      ) : (
        <ul className="activity-list">
          {activities.map((activity) => (
            <li
              key={activity.id}
              className={`activity-item ${openActivity === activity.id ? 'active' : ''} ${activity.status === 'past' ? 'past' : ''}`}
              onClick={() => toggleActivity(activity.id)}
            >
              <div className="activity-header">
                <strong>{activity.name}</strong>
                <span className="activity-date">– {formatDate(activity.date)}</span>
                <span className="activity-toggle">{openActivity === activity.id ? '−' : '+'}</span>
              </div>

              {openActivity === activity.id && (
                <div className="activity-details">
                  {activity.comment && (
                    <p className="activity-description" style={{ whiteSpace: 'pre-wrap' }}>
                      {activity.comment}
                    </p>
                  )}
                  <div className="activity-info-grid">
                    {activity.place && (
                      <div>
                        <strong>📍 Locatie:</strong> {activity.place}
                      </div>
                    )}
                    {activity.startHour && activity.stopHour && (
                      <div>
                        <strong>🕒 Tijd:</strong> {formatTime(activity.startHour)} - {formatTime(activity.stopHour)}
                      </div>
                    )}
                    {activity.info && (
                      <div className="activity-extra-info">
                        <strong>ℹ️ Info:</strong> {activity.info}
                      </div>
                    )}
                  </div>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
      <p>Wil je meedoen? Neem contact met ons op of kom gewoon langs!</p>
    </section>
  );
}
export default ActivitiesPage;
