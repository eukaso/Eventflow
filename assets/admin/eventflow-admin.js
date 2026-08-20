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
  const setup = document.getElementById('eventflow-setup');
  const setupClose = document.getElementById('eventflow-setup-close');
  const setupNotice = document.getElementById('eventflow-setup-notice');
  const eventForm = document.getElementById('eventflow-event-form');
  const configurationForm = document.getElementById('eventflow-configuration-form');
  const venueForm = document.getElementById('eventflow-venue-form');
  const venueSelect = document.getElementById('eventflow-event-venue');
  const refresh = document.getElementById('eventflow-refresh');
  const bootstrapNotice = document.getElementById('eventflow-bootstrap-notice');
  let activeEvent = null;
  let activeEventEtag = null;
  let activeConfigurationEtag = null;

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

  const showOverview = (event, etag = null) => {
    activeEvent = event;
    activeEventEtag = etag;
    activeConfigurationEtag = null;
    eventsView.hidden = true;
    overview.hidden = false;
    setup.hidden = true;
    overviewFacts.hidden = false;
    overviewTitle.textContent = String(event.name || 'Untitled event');
    overviewStatus.textContent = String(event.status || 'unknown');
    overviewFacts.replaceChildren();
    addFact('Timezone', String(event.timezone || 'Not set'));
    addFact('Starts', event.starts_at ? String(event.starts_at) : 'Not scheduled');
    addFact('Ends', event.ends_at ? String(event.ends_at) : 'Not scheduled');
    addFact('Revision', String(event.revision || 0));
    overviewActions.replaceChildren();
    overviewMessage.textContent = '';

    const setupButton = document.createElement('button');
    setupButton.className = 'button button-secondary';
    setupButton.type = 'button';
    setupButton.textContent = 'Edit setup';
    setupButton.addEventListener('click', openSetup);
    overviewActions.appendChild(setupButton);

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
      const { payload, etag } = await requestJson(`events/${encodeURIComponent(String(eventId))}`);
      showOverview(payload.data || {}, etag);
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

  const field = (form, name) => form.elements.namedItem(name);
  const nullableText = (form, name) => {
    const value = String(field(form, name).value || '').trim();
    return value === '' ? null : value;
  };
  const setField = (form, name, value) => {
    const control = field(form, name);
    if (control.type === 'checkbox') control.checked = Boolean(value);
    else control.value = value === null || value === undefined ? '' : String(value);
  };
  const disableForm = (form, disabled) => {
    Array.from(form.elements).forEach((control) => { control.disabled = disabled; });
  };
  const mutationHeaders = (etag = null) => {
    const headers = {
      'Content-Type': 'application/json',
      'Idempotency-Key': idempotencyKey(),
    };
    if (etag) headers['If-Match'] = etag;
    return headers;
  };

  const fillEventForm = (event) => {
    ['name', 'slug', 'timezone', 'starts_at', 'ends_at'].forEach((name) => setField(eventForm, name, event[name]));
    setField(eventForm, 'venue_id', event.venue_id);
    disableForm(eventForm, event.status !== 'draft');
  };

  const fillConfigurationForm = (configuration) => {
    ['welcome_message', 'confirmation_message', 'dress_code', 'confirmation_opens_at', 'confirmation_closes_at', 'seating_mode', 'allow_guest_edits', 'automatic_seating_enabled']
      .forEach((name) => setField(configurationForm, name, configuration[name]));
    disableForm(configurationForm, false);
  };

  const loadVenues = async (selectedVenueId = null) => {
    const { payload } = await requestJson('venues?limit=100');
    venueSelect.replaceChildren();
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = 'No venue selected';
    venueSelect.appendChild(empty);
    (Array.isArray(payload.data) ? payload.data : []).forEach((venue) => {
      const option = document.createElement('option');
      option.value = String(venue.id);
      option.textContent = venue.city ? `${venue.name} — ${venue.city}` : String(venue.name);
      venueSelect.appendChild(option);
    });
    venueSelect.value = selectedVenueId ? String(selectedVenueId) : '';
    venueSelect.disabled = activeEvent?.status !== 'draft';
    disableForm(venueForm, false);
  };

  const openSetup = async () => {
    if (!activeEvent) return;
    overviewFacts.hidden = true;
    setup.hidden = false;
    setupNotice.textContent = 'Loading current setup…';
    fillEventForm(activeEvent);
    disableForm(configurationForm, true);
    disableForm(venueForm, true);

    const eventId = encodeURIComponent(String(activeEvent.id));
    const [configurationResult, venueResult] = await Promise.allSettled([
      requestJson(`events/${eventId}/configuration`),
      loadVenues(activeEvent.venue_id),
    ]);
    const messages = [];
    if (configurationResult.status === 'fulfilled') {
      activeConfigurationEtag = configurationResult.value.etag;
      fillConfigurationForm(configurationResult.value.payload.data || {});
    } else {
      activeConfigurationEtag = null;
      messages.push('Guest and seating settings are unavailable.');
    }
    if (venueResult.status === 'rejected') {
      venueSelect.disabled = true;
      messages.push('Venue access is unavailable.');
    }
    if (activeEvent.status !== 'draft') messages.push('Event details are read-only after activation.');
    setupNotice.textContent = messages.join(' ') || 'Current setup loaded.';
  };

  const refreshSetup = async (eventId, message) => {
    const refreshed = await loadOverview(eventId);
    if (!refreshed) return false;
    await openSetup();
    setupNotice.textContent = message;
    return true;
  };

  const submitEventSetup = async (submissionEvent) => {
    submissionEvent.preventDefault();
    if (!activeEvent || !activeEventEtag || activeEvent.status !== 'draft') return;
    disableForm(eventForm, true);
    setupNotice.textContent = 'Saving Event details…';
    const eventId = activeEvent.id;
    try {
      const venueId = String(field(eventForm, 'venue_id').value || '');
      await requestJson(`events/${encodeURIComponent(String(eventId))}`, {
        method: 'PATCH',
        headers: mutationHeaders(activeEventEtag),
        body: JSON.stringify({
          name: String(field(eventForm, 'name').value).trim(),
          slug: String(field(eventForm, 'slug').value).trim(),
          timezone: String(field(eventForm, 'timezone').value).trim(),
          starts_at: nullableText(eventForm, 'starts_at'),
          ends_at: nullableText(eventForm, 'ends_at'),
          venue_id: venueId === '' ? null : Number(venueId),
        }),
      });
      const refreshed = await refreshSetup(eventId, 'Event details saved.');
      if (!refreshed) setupNotice.textContent = 'The update was accepted, but current Event details could not be confirmed. Refresh before retrying.';
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      setupNotice.textContent = `Event details were not saved. Refresh before retrying.${reference}`;
      disableForm(eventForm, false);
    }
  };

  const submitConfiguration = async (submissionEvent) => {
    submissionEvent.preventDefault();
    if (!activeEvent || !activeConfigurationEtag) return;
    disableForm(configurationForm, true);
    setupNotice.textContent = 'Saving guest and seating settings…';
    const eventId = activeEvent.id;
    try {
      await requestJson(`events/${encodeURIComponent(String(eventId))}/configuration`, {
        method: 'PATCH',
        headers: mutationHeaders(activeConfigurationEtag),
        body: JSON.stringify({
          welcome_message: nullableText(configurationForm, 'welcome_message'),
          confirmation_message: nullableText(configurationForm, 'confirmation_message'),
          dress_code: nullableText(configurationForm, 'dress_code'),
          confirmation_opens_at: nullableText(configurationForm, 'confirmation_opens_at'),
          confirmation_closes_at: nullableText(configurationForm, 'confirmation_closes_at'),
          seating_mode: String(field(configurationForm, 'seating_mode').value),
          allow_guest_edits: field(configurationForm, 'allow_guest_edits').checked,
          automatic_seating_enabled: field(configurationForm, 'automatic_seating_enabled').checked,
        }),
      });
      const refreshed = await refreshSetup(eventId, 'Guest and seating settings saved.');
      if (!refreshed) setupNotice.textContent = 'The update was accepted, but current settings could not be confirmed. Refresh before retrying.';
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      setupNotice.textContent = `Settings were not saved. Refresh before retrying.${reference}`;
      disableForm(configurationForm, false);
    }
  };

  const submitVenue = async (submissionEvent) => {
    submissionEvent.preventDefault();
    if (!activeEvent) return;
    disableForm(venueForm, true);
    setupNotice.textContent = 'Creating venue…';
    try {
      const capacity = String(field(venueForm, 'default_capacity').value || '');
      const { payload } = await requestJson('venues', {
        method: 'POST',
        headers: mutationHeaders(),
        body: JSON.stringify({
          name: String(field(venueForm, 'name').value).trim(),
          city: nullableText(venueForm, 'city'),
          country_code: nullableText(venueForm, 'country_code')?.toUpperCase() || null,
          default_capacity: capacity === '' ? null : Number(capacity),
        }),
      });
      venueForm.reset();
      await loadVenues(payload.data?.id || activeEvent.venue_id);
      setupNotice.textContent = 'Venue created. Save Event details to assign it.';
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      setupNotice.textContent = `Venue was not created.${reference}`;
      disableForm(venueForm, false);
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
      activeEvent = null;
      activeEventEtag = null;
      activeConfigurationEtag = null;
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
  setupClose.addEventListener('click', () => {
    setup.hidden = true;
    overviewFacts.hidden = false;
    overviewMessage.textContent = 'Setup closed.';
  });
  eventForm.addEventListener('submit', submitEventSetup);
  configurationForm.addEventListener('submit', submitConfiguration);
  venueForm.addEventListener('submit', submitVenue);
  loadEvents();
})();
