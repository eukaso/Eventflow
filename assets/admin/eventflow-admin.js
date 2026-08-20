(() => {
  'use strict';

  const root = document.getElementById('eventflow-admin');
  if (!root) return;

  const config = window.EventFlowAdmin || {};
  const region = document.getElementById('eventflow-event-region');
  const status = document.getElementById('eventflow-status');
  const list = document.getElementById('eventflow-event-list');
  const refresh = document.getElementById('eventflow-refresh');
  const bootstrapNotice = document.getElementById('eventflow-bootstrap-notice');

  const setStatus = (message, busy = false) => {
    status.textContent = message;
    region.setAttribute('aria-busy', busy ? 'true' : 'false');
  };

  const appendText = (parent, tagName, className, value) => {
    const element = document.createElement(tagName);
    element.className = className;
    element.textContent = value;
    parent.appendChild(element);
  };

  const renderEvents = (events) => {
    list.replaceChildren();
    if (!events.length) {
      setStatus('No accessible events were found.');
      return;
    }

    events.forEach((event) => {
      const card = document.createElement('article');
      card.className = 'eventflow-event-card';
      card.dataset.eventId = String(event.id);
      appendText(card, 'p', 'eventflow-event-card__status', String(event.status || 'unknown'));
      appendText(card, 'h2', 'eventflow-event-card__title', String(event.name || 'Untitled event'));
      appendText(card, 'p', 'eventflow-event-card__meta', event.starts_at ? String(event.starts_at) : 'Date not scheduled');
      list.appendChild(card);
    });
    setStatus(`${events.length} accessible event${events.length === 1 ? '' : 's'} loaded.`);
  };

  const loadEvents = async () => {
    if (!config.ready) {
      bootstrapNotice.hidden = false;
      refresh.disabled = true;
      setStatus(`EventFlow status: ${config.bootstrapState || 'not ready'}.`);
      return;
    }

    refresh.disabled = true;
    setStatus('Loading accessible events…', true);
    try {
      const response = await fetch(`${config.restUrl}events?limit=20`, {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': String(config.nonce || '') },
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || payload.code || 'request_failed');
      renderEvents(Array.isArray(payload.data) ? payload.data : []);
    } catch (error) {
      list.replaceChildren();
      setStatus('Events could not be loaded. Check your access and try again.');
    } finally {
      refresh.disabled = false;
      region.setAttribute('aria-busy', 'false');
    }
  };

  refresh.addEventListener('click', loadEvents);
  loadEvents();
})();
