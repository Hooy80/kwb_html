import React, { useState } from 'react';
import './App.css';
import Header from './components/header';
import SidebarLeft from './components/sidebarleft';
import SidebarRight from './components/sidebarright';
import Footer from './components/footer';
import HomePage from './components/pages/home';
import AboutPage from './components/pages/about';
import ActivitiesPage from './components/pages/activiteiten';
import ContactPage from './components/pages/contact';
import MediaPage from './components/pages/media';

function App() {
  const [currentPage, setCurrentPage] = useState('home');

  const renderPage = () => {
    switch (currentPage) {
      case 'home': return <HomePage />;
      case 'about': return <AboutPage />;
      case 'activities': return <ActivitiesPage />;
      case 'contact': return <ContactPage />;
      case 'media': return <MediaPage />;
      default: return <HomePage />;
    }
  };

  return (
    <>
      <Header currentPage={currentPage} setCurrentPage={setCurrentPage} />
      <div className="main-container">
        <SidebarLeft />
        <main className="content">
          {renderPage()}
        </main>
        <SidebarRight />
      </div>
      <Footer />
    </>
  );
}

export default App;
