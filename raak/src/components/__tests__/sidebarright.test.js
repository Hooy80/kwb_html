import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import SidebarRight from '../sidebarright';

describe('SidebarRight', () => {
  const mockActivity = {
    id: 1,
    date: '2025-12-01',
    name: 'Smakelijk Wandelen Test',
    startHour: null,
    stopHour: null,
    place: 'Park',
    comment: '',
    info: '',
    inschrijving: 1,
    status: 'future',
    daysAgo: -1,
    photoFilename: '20251201_SmakelijkWandelenTest.jpg'
  };

  beforeEach(() => {
    global.fetch = jest.fn((url) => {
      if (url.includes('calendar.php')) {
        return Promise.resolve({ ok: true, json: () => Promise.resolve([mockActivity]) });
      }
      return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
    });
  });

  afterEach(() => {
    if (global.fetch && global.fetch.mockRestore) global.fetch.mockRestore();
    global.fetch = undefined;
  });

  test('renders image for next activity when present', async () => {
    render(<SidebarRight />);

    // wait for the image to appear
    await waitFor(() => expect(global.fetch).toHaveBeenCalled());

    const img = await screen.findByAltText(/Smakelijk Wandelen Test/i);
    expect(img).toBeTruthy();
  });

  test('shows fallback text when image fails to load', async () => {
    render(<SidebarRight />);
    await waitFor(() => expect(global.fetch).toHaveBeenCalled());

    const img = await screen.findByAltText(/Smakelijk Wandelen Test/i);
    // simulate image load error
    fireEvent.error(img);

    // fallback paragraph with activity name should appear
    await waitFor(() => {
      expect(screen.getByText(/Smakelijk Wandelen Test/i)).toBeTruthy();
    });
  });
});
