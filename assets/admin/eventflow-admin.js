(() => {
  'use strict';

  const root = document.getElementById('eventflow-admin');
  if (!root) return;

  const config = window.EventFlowAdmin || {};
  const region = document.getElementById('eventflow-event-region');
  const status = document.getElementById('eventflow-status');
  const list = document.getElementById('eventflow-event-list');
  const eventsView = document.getElementById('eventflow-events-view');
  const overview = document.getElementById('eventflow-overview');
  const overviewBack = document.getElementById('eventflow-overview-back');
  const overviewTitle = document.getElementById('eventflow-overview-title');
  const overviewStatus = document.getElementById('eventflow-overview-status');
  const overviewFacts = document.getElementById('eventflow-overview-facts');
  const overviewActions = document.getElementById('eventflow-overview-actions');
  const overviewMessage = document.getElementById('eventflow-overview-message');
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

  const requestHeaders = (additional = {}) => ({
    'X-WP-Nonce': String(config.nonce || ''),
    ...additional,
  });

  const requestJson = async (path, options = {}) => {
    const response = await fetch(`${config.restUrl}${path}`, {
      credentials: 'same-origin',
      ...options,
      headers: requestHeaders(options.headers || {}),
    });
    const payload = await response.json();
    if (!response.ok) {
      const failure = new Error(payload.message || payload.code || 'request_failed');
      failure.code = payload.code || 'request_failed';
      failure.requestId = payload.request_id || response.headers.get('X-Request-ID') || '';
      throw failure;
    }
    return { payload, etag: response.headers.get('ETag') };
  };

  const idempotencyKey = () => {
    if (!window.crypto || !window.crypto.getRandomValues) throw new Error('secure_random_unavailable');
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return `eventflow-ui-${Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('')}`;
  };

  const lifecycleActions = {
    draft: ['activate', 'cancel'],
    active: ['complete', 'cancel'],
    completed: ['archive'],
    cancelled: ['archive'],
    archived: ['restore'],
  };

  const actionLabel = (action) => ({
    activate: 'Activate event',
    complete: 'Complete event',
    cancel: 'Cancel event',
    archive: 'Archive event',
    restore: 'Restore event',
  })[action] || action;

  const addFact = (label, value) => {
    const term = document.createElement('dt');
    const description = document.createElement('dd');
    term.textContent = label;
    description.textContent = value;
    overviewFacts.append(term, description);
  };

  const showOverview = (event) => {
    eventsView.hidden = true;
    overview.hidden = false;
    overviewTitle.textContent = String(event.name || 'Untitled event');
    overviewStatus.textContent = String(event.status || 'unknown');
    overviewFacts.replaceChildren();
    addFact('Timezone', String(event.timezone || 'Not set'));
    addFact('Starts', event.starts_at ? String(event.starts_at) : 'Not scheduled');
    addFact('Ends', event.ends_at ? String(event.ends_at) : 'Not scheduled');
    addFact('Revision', String(event.revision || 0));
    overviewActions.replaceChildren();
    overviewMessage.textContent = '';

    (lifecycleActions[event.status] || []).forEach((action) => {
      const button = document.createElement('button');
      button.className = action === 'cancel' || action === 'archive' ? 'button button-link-delete' : 'button button-primary';
      button.type = 'button';
      button.textContent = actionLabel(action);
      button.addEventListener('click', () => transitionEvent(event.id, action));
      overviewActions.appendChild(button);
    });
    setStatus(`Overview loaded for ${event.name || 'event'}.`);
  };

  const loadOverview = async (eventId) => {
    setStatus('Loading event overview…', true);
    try {
      const { payload } = await requestJson(`events/${encodeURIComponent(String(eventId))}`);
      showOverview(payload.data || {});
      return true;
    } catch (error) {
      overviewMessage.textContent = 'The Event overview could not be loaded. Return to the Event list and try again.';
      setStatus('Event overview unavailable.');
      return false;
    }
  };

  const transitionEvent = async (eventId, action) => {
    if ((action === 'cancel' || action === 'archive')
      && !window.confirm(`${actionLabel(action)}? This change is recorded in the audit history.`)) return;

    const buttons = overviewActions.querySelectorAll('button');
    buttons.forEach((button) => { button.disabled = true; });
    overviewMessage.textContent = `${actionLabel(action)} in progress…`;
    try {
      const key = idempotencyKey();
      await requestJson(`events/${encodeURIComponent(String(eventId))}/${action}`, {
        method: 'POST',
        headers: { 'Idempotency-Key': key },
      });
      const refreshed = await loadOverview(eventId);
      overviewMessage.textContent = refreshed
        ? `${actionLabel(action)} completed.`
        : 'The action was accepted, but the latest Event state could not be confirmed. Refresh before retrying.';
    } catch (error) {
      const requestReference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      overviewMessage.textContent = `The latest Event state could not be confirmed. Refresh before retrying.${requestReference}`;
      buttons.forEach((button) => { button.disabled = false; });
      setStatus('Event action failed.');
    }
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
      const open = document.createElement('button');
      open.className = 'button button-secondary';
      open.type = 'button';
      open.textContent = 'Open overview';
      open.addEventListener('click', () => loadOverview(event.id));
      card.appendChild(open);
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
      const { payload } = await requestJson('events?limit=20');
      overview.hidden = true;
      eventsView.hidden = false;
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
  overviewBack.addEventListener('click', loadEvents);
  loadEvents();
})();
