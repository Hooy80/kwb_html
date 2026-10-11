import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import SidebarLeft from '../sidebarleft';

describe('SidebarLeft registration buttons', () => {
  beforeEach(() => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve([
        {
          id: 10,
          name: 'Open activiteit',
          inschrijving: 1,
          inschrijvingen_start: '2999-01-01',
          inschrijvingen_end: '2999-12-31'
        },
        {
          id: 11,
          name: 'Verlopen inschrijving',
          inschrijving: 1,
          inschrijvingen_start: '2000-01-01',
          inschrijvingen_end: '2000-12-31'
        },
        {
          id: 12,
          name: 'Geen inschrijving',
          inschrijving: 0
        }
      ])
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
    global.fetch = undefined;
  });

  test('shows the heading and buttons for activities marked active in the calendar', async () => {
    render(<SidebarLeft />);

    expect(await screen.findByRole('heading', { name: 'Inschrijvingen' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Open activiteit' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Verlopen inschrijving' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Geen inschrijving' })).toBeNull();
  });

  test('opens the selected generic registration form', async () => {
    render(<SidebarLeft />);

    fireEvent.click(await screen.findByRole('button', { name: 'Open activiteit' }));

    await waitFor(() => {
      expect(screen.getByTitle('Inschrijving Open activiteit').getAttribute('src'))
        .toBe('/php/calendar_inschrijvingen.php?id_act=10');
    });
  });

  test('uses the separate Smakelijk Wandelen form', async () => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve([{
        id: 13,
        name: 'Smakelijk Wandelen',
        inschrijving: 1,
        inschrijvingen_start: '2999-01-01',
        inschrijvingen_end: '2999-12-31'
      }])
    });
    render(<SidebarLeft />);

    fireEvent.click(await screen.findByRole('button', { name: 'Smakelijk Wandelen' }));

    expect(screen.getByTitle('Inschrijving Smakelijk Wandelen').getAttribute('src'))
      .toBe('/form_smakelijk_wandelen.html');
  });
});
