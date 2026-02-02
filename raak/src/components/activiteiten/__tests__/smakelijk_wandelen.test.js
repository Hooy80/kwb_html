import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import SmakelijkWandelenPage from '../smakelijk_wandelen';

describe('SmakelijkWandelenPage', () => {
  const mockActivity = {
    id: 2,
    date: '2025-11-15',
    name: 'Smakelijk Wandelen',
    place: 'Bos',
    inschrijving: 1
  };

  beforeEach(() => {
    global.fetch = jest.fn((url) => {
      if (url.includes('calendar.php')) {
        return Promise.resolve({ ok: true, json: () => Promise.resolve([mockActivity]) });
      }
      return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true }) });
    });
  });

  afterEach(() => {
    if (global.fetch && global.fetch.mockRestore) global.fetch.mockRestore();
    global.fetch = undefined;
  });

  test('loads activity info and displays date and place', async () => {
    render(<SmakelijkWandelenPage />);

    await waitFor(() => expect(global.fetch).toHaveBeenCalled());
    expect(await screen.findByText(/Bos/)).toBeTruthy();
    expect(await screen.findByText(/Inschrijving Smakelijk Wandelen/)).toBeTruthy();
  });
});
