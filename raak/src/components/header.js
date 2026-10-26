import { useState } from 'react';

// Header Component
function Header({ currentPage, setCurrentPage }) {
  const [menuOpen, setMenuOpen] = useState(false);

  const handleMenuClick = (page) => {
    setCurrentPage(page);
    setMenuOpen(false);
  };

  return (
    <header className="topnav">
      <button className="hamburger" onClick={() => setMenuOpen(!menuOpen)}>
        <span></span>
        <span></span>
        <span></span>
      </button>
      <nav className={menuOpen ? 'nav-open' : ''}>
        <ul>
          <li><a href="#" className={currentPage === 'home' ? 'active' : ''} onClick={() => handleMenuClick('home')}>Home</a></li>
          <li><a href="#" className={currentPage === 'about' ? 'active' : ''} onClick={() => handleMenuClick('about')}>Over ons</a></li>
          <li><a href="#" className={currentPage === 'contact' ? 'active' : ''} onClick={() => handleMenuClick('contact')}>Contact</a></li>
          <li><a href="#" className={currentPage === 'activities' ? 'active' : ''} onClick={() => handleMenuClick('activities')}>Activiteiten</a></li>
          <li><a href="#" className={currentPage === 'media' ? 'active' : ''} onClick={() => handleMenuClick('media')}>Media</a></li>
        </ul>
      </nav>
    </header>
  );
}
export default Header;