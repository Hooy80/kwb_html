import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';
import App from '../../../raak/public/bestuur/App';
import Login from '../../../raak/public/bestuur/Login';

function Root() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  
  useEffect(() => {
    // Check of user al ingelogd is (sessionStorage/localStorage)
    const token = sessionStorage.getItem('adminToken');
    setIsAuthenticated(!!token);
  }, []);
  
  const handleLogin = () => {
    setIsAuthenticated(true);
  };
  
  const handleLogout = () => {
    sessionStorage.removeItem('adminToken');
    setIsAuthenticated(false);
  };
  
  // Als niet ingelogd → toon Login
  // Als ingelogd → toon App
  return isAuthenticated ? (
    <App onLogout={handleLogout} />
  ) : (
    <Login onLogin={handleLogin} />
  );
}

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <Root />
  </React.StrictMode>
);