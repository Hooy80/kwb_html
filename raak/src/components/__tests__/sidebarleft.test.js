import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import SidebarLeft from '../sidebarleft';

describe('SidebarLeft registration buttons', () => {
  beforeEach(() => {
    const today = new Date();
    const dateOffset = (days) => {
      const date = new Date(today);
      date.setDate(date.getDate() + days);
      return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    };

    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve([
        {
          id: 10,
          name: 'Open activiteit',
          inschrijving: 1,
          inschrijvingen_start: dateOffset(-1),
          inschrijvingen_end: dateOffset(1)
        },
        {
          id: 11,
          name: 'Verlopen inschrijving',
          inschrijving: 1,
          inschrijvingen_start: dateOffset(-10),
          inschrijvingen_end: dateOffset(-1)
        },
        {
          id: 14,
          name: 'Toekomstige inschrijving',
          inschrijving: 1,
          inschrijvingen_start: dateOffset(1),
          inschrijvingen_end: dateOffset(10)
        },
        {
          id: 15,
          name: 'Inschrijving zonder datums',
          inschrijving: 1
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

  test('shows registration buttons only within their registration dates', async () => {
    render(<SidebarLeft />);

    expect(await screen.findByRole('heading', { name: 'Inschrijvingen' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Open activiteit' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Verlopen inschrijving' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Toekomstige inschrijving' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Inschrijving zonder datums' })).toBeNull();
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

  test('uses the activity registration form for Smakelijk Wandelen', async () => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve([{
        id: 13,
        name: 'Smakelijk Wandelen',
        inschrijving: 1,
        inschrijvingen_start: (() => {
          const date = new Date();
          date.setDate(date.getDate() - 1);
          return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        })(),
        inschrijvingen_end: (() => {
          const date = new Date();
          date.setDate(date.getDate() + 1);
          return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        })()
      }])
    });
    render(<SidebarLeft />);

    fireEvent.click(await screen.findByRole('button', { name: 'Smakelijk Wandelen' }));

    expect(screen.getByTitle('Inschrijving Smakelijk Wandelen').getAttribute('src'))
      .toBe('/php/calendar_inschrijvingen.php?id_act=13');
  });
});
