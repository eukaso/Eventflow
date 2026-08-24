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
  const people = document.getElementById('eventflow-people');
  const peopleClose = document.getElementById('eventflow-people-close');
  const peopleNotice = document.getElementById('eventflow-people-notice');
  const membershipForm = document.getElementById('eventflow-membership-form');
  const invitationForm = document.getElementById('eventflow-invitation-form');
  const invitationSubmit = document.getElementById('eventflow-invitation-submit');
  const invitationEditCancel = document.getElementById('eventflow-invitation-edit-cancel');
  const invitationFilter = document.getElementById('eventflow-invitation-filter');
  const invitationStateFilter = document.getElementById('eventflow-invitation-state-filter');
  const invitationFilterStatus = document.getElementById('eventflow-invitation-filter-status');
  const attendeeForm = document.getElementById('eventflow-attendee-form');
  const membershipList = document.getElementById('eventflow-membership-list');
  const invitationList = document.getElementById('eventflow-invitation-list');
  const attendeeList = document.getElementById('eventflow-attendee-list');
  const attendeeInvitation = document.getElementById('eventflow-attendee-invitation');
  const credential = document.getElementById('eventflow-credential');
  const credentialToken = document.getElementById('eventflow-credential-token');
  const credentialCopy = document.getElementById('eventflow-credential-copy');
  const credentialClear = document.getElementById('eventflow-credential-clear');
  const peopleTabs = ['memberships', 'invitations', 'attendees'];
  const seating = document.getElementById('eventflow-seating');
  const seatingClose = document.getElementById('eventflow-seating-close');
  const seatingNotice = document.getElementById('eventflow-seating-notice');
  const seatingReadiness = document.getElementById('eventflow-seating-readiness');
  const tableForm = document.getElementById('eventflow-table-form');
  const groupForm = document.getElementById('eventflow-group-form');
  const placementForm = document.getElementById('eventflow-placement-form');
  const recommendationForm = document.getElementById('eventflow-recommendation-form');
  const tableList = document.getElementById('eventflow-table-list');
  const groupList = document.getElementById('eventflow-group-list');
  const placementAttendee = document.getElementById('eventflow-placement-attendee');
  const placementTable = document.getElementById('eventflow-placement-table');
  const placementSeat = document.getElementById('eventflow-placement-seat');
  const recommendationResult = document.getElementById('eventflow-recommendation-result');
  const reception = document.getElementById('eventflow-reception');
  const receptionClose = document.getElementById('eventflow-reception-close');
  const receptionNotice = document.getElementById('eventflow-reception-notice');
  const receptionSearchForm = document.getElementById('eventflow-reception-search-form');
  const receptionResults = document.getElementById('eventflow-reception-results');
  const receptionBulk = document.getElementById('eventflow-reception-bulk');
  const receptionSelection = document.getElementById('eventflow-reception-selection');
  const receptionBulkCheckIn = document.getElementById('eventflow-reception-bulk-checkin');
  const communications = document.getElementById('eventflow-communications');
  const communicationsClose = document.getElementById('eventflow-communications-close');
  const communicationsNotice = document.getElementById('eventflow-communications-notice');
  const communicationTabs = ['templates', 'campaigns', 'messages'];
  const templateForm = document.getElementById('eventflow-template-form');
  const campaignForm = document.getElementById('eventflow-campaign-form');
  const messageFilterForm = document.getElementById('eventflow-message-filter-form');
  const templateList = document.getElementById('eventflow-template-list');
  const campaignList = document.getElementById('eventflow-campaign-list');
  const messageList = document.getElementById('eventflow-message-list');
  const campaignTemplate = document.getElementById('eventflow-campaign-template');
  const invitationComposer = document.getElementById('eventflow-invitation-composer');
  const invitationChannel = document.getElementById('eventflow-invitation-channel');
  const invitationSubject = document.getElementById('eventflow-invitation-subject');
  const invitationImage = document.getElementById('eventflow-invitation-image');
  const invitationImageChoose = document.getElementById('eventflow-invitation-image-choose');
  const invitationMessage = document.getElementById('eventflow-invitation-message');
  const invitationTestName = document.getElementById('eventflow-invitation-test-name');
  const invitationTestAddress = document.getElementById('eventflow-invitation-test-address');
  const invitationTestSend = document.getElementById('eventflow-invitation-test-send');
  const invitationTestStatus = document.getElementById('eventflow-invitation-test-status');
  const invitationRecipientSearch = document.getElementById('eventflow-invitation-recipient-search');
  const invitationPhoneRegion = document.getElementById('eventflow-invitation-phone-region');
  const invitationSelectVisible = document.getElementById('eventflow-invitation-select-visible');
  const invitationClearSelection = document.getElementById('eventflow-invitation-clear-selection');
  const invitationSelectionStatus = document.getElementById('eventflow-invitation-selection-status');
  const invitationRecipientList = document.getElementById('eventflow-invitation-recipient-list');
  const invitationReview = document.getElementById('eventflow-invitation-review');
  const invitationSend = document.getElementById('eventflow-invitation-send');
  const invitationReviewStatus = document.getElementById('eventflow-invitation-review-status');
  const templatePreview = document.getElementById('eventflow-template-preview');
  const templatePreviewSubject = document.getElementById('eventflow-template-preview-subject');
  const templatePreviewBody = document.getElementById('eventflow-template-preview-body');
  const templatePreviewClear = document.getElementById('eventflow-template-preview-clear');
  const messageDetail = document.getElementById('eventflow-message-detail');
  const messageDetailTitle = document.getElementById('eventflow-message-detail-title');
  const messageDetailRecipient = document.getElementById('eventflow-message-detail-recipient');
  const messageDetailContent = document.getElementById('eventflow-message-detail-content');
  const messageDetailClear = document.getElementById('eventflow-message-detail-clear');
  const governance = document.getElementById('eventflow-governance');
  const governanceClose = document.getElementById('eventflow-governance-close');
  const governanceNotice = document.getElementById('eventflow-governance-notice');
  const governanceTabs = ['imports', 'exports', 'privacy', 'audit', 'diagnostics'];
  const importForm = document.getElementById('eventflow-import-form');
  const exportForm = document.getElementById('eventflow-export-form');
  const privacyActionForm = document.getElementById('eventflow-privacy-action-form');
  const holdForm = document.getElementById('eventflow-hold-form');
  const auditFilterForm = document.getElementById('eventflow-audit-filter-form');
  const importList = document.getElementById('eventflow-import-list');
  const exportList = document.getElementById('eventflow-export-list');
  const privacyActionList = document.getElementById('eventflow-privacy-action-list');
  const holdList = document.getElementById('eventflow-hold-list');
  const auditList = document.getElementById('eventflow-audit-list');
  const importDetail = document.getElementById('eventflow-import-detail');
  const importDetailContent = document.getElementById('eventflow-import-detail-content');
  const importDetailClear = document.getElementById('eventflow-import-detail-clear');
  const auditIntegrity = document.getElementById('eventflow-audit-integrity');
  const auditIntegrityResult = document.getElementById('eventflow-audit-integrity-result');
  const auditDetail = document.getElementById('eventflow-audit-detail');
  const auditDetailContent = document.getElementById('eventflow-audit-detail-content');
  const auditDetailClear = document.getElementById('eventflow-audit-detail-clear');
  const diagnosticsLoad = document.getElementById('eventflow-diagnostics-load');
  const diagnosticsDetail = document.getElementById('eventflow-diagnostics-detail');
  const diagnosticsContent = document.getElementById('eventflow-diagnostics-content');
  const diagnosticsClear = document.getElementById('eventflow-diagnostics-clear');
  const refresh = document.getElementById('eventflow-refresh');
  const bootstrapNotice = document.getElementById('eventflow-bootstrap-notice');
  let activeEvent = null;
  let activeEventEtag = null;
  let activeConfigurationEtag = null;
  let credentialClearTimer = null;
  let editingInvitationId = null;
  let editingInvitationEtag = null;
  let loadedInvitations = [];
  let seatingTables = [];
  let seatingRecommendation = null;
  let receptionAttendees = [];
  let communicationTemplates = [];
  let invitationRecipients = [];
  let selectedInvitationRecipients = new Set();
  let preparedInvitationCampaign = null;

  const setStatus = (message, busy = false) => {
    status.textContent = message;
    region.setAttribute('aria-busy', busy ? 'true' : 'false');
  };

  const reportInvalidControl = (validationEvent) => {
    const control = validationEvent.target;
    const form = control.form;
    if (!form || !form.id) return;
    const summaryId = `${form.id}-error-summary`;
    let summary = document.getElementById(summaryId);
    if (!summary) {
      summary = document.createElement('p');
      summary.className = 'eventflow-form-error';
      summary.id = summaryId;
      summary.setAttribute('role', 'alert');
      summary.textContent = 'Check the highlighted fields and correct the information before continuing.';
      form.prepend(summary);
    }
    control.setAttribute('aria-invalid', 'true');
    const descriptions = new Set(String(control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    descriptions.add(summaryId);
    control.setAttribute('aria-describedby', Array.from(descriptions).join(' '));
  };

  const clearInvalidControl = (inputEvent) => {
    const control = inputEvent.target;
    if (!control.matches('input, select, textarea') || !control.validity?.valid) return;
    control.removeAttribute('aria-invalid');
    const form = control.form;
    if (!form || form.querySelector('[aria-invalid="true"]')) return;
    const summary = document.getElementById(`${form.id}-error-summary`);
    if (summary) summary.remove();
  };

  const configureTabs = (names, select) => {
    names.forEach((name, index) => {
      const tab = document.getElementById(`eventflow-${name}-tab`);
      tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
      tab.addEventListener('click', () => select(name));
      tab.addEventListener('keydown', (keyboardEvent) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(keyboardEvent.key)) return;
        keyboardEvent.preventDefault();
        const nextIndex = keyboardEvent.key === 'Home' ? 0
          : keyboardEvent.key === 'End' ? names.length - 1
            : (index + (keyboardEvent.key === 'ArrowRight' ? 1 : -1) + names.length) % names.length;
        select(names[nextIndex]);
        document.getElementById(`eventflow-${names[nextIndex]}-tab`).focus();
      });
    });
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

  const requestAllPages = async (path, nextAfterKey) => {
    const data = [];
    let after = null;
    for (let page = 0; page < 100; page += 1) {
      const query = `limit=100${after === null ? '' : `&after=${encodeURIComponent(String(after))}`}`;
      const result = await requestJson(`${path}?${query}`);
      data.push(...(Array.isArray(result.payload.data) ? result.payload.data : []));
      after = result.payload.meta?.[nextAfterKey] ?? null;
      if (after === null) return { payload: { ...result.payload, data }, etag: result.etag };
    }
    throw new Error('pagination_limit_exceeded');
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
    clearCredential();
    activeEvent = event;
    activeEventEtag = etag;
    activeConfigurationEtag = null;
    eventsView.hidden = true;
    overview.hidden = false;
    setup.hidden = true;
    people.hidden = true;
    seating.hidden = true;
    reception.hidden = true;
    communications.hidden = true;
    governance.hidden = true;
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

    const peopleButton = document.createElement('button');
    peopleButton.className = 'button button-secondary';
    peopleButton.type = 'button';
    peopleButton.textContent = 'Manage people';
    peopleButton.addEventListener('click', openPeople);
    overviewActions.appendChild(peopleButton);

    const seatingButton = document.createElement('button');
    seatingButton.className = 'button button-secondary';
    seatingButton.type = 'button';
    seatingButton.textContent = 'Plan seating';
    seatingButton.addEventListener('click', openSeating);
    overviewActions.appendChild(seatingButton);

    const receptionButton = document.createElement('button');
    receptionButton.className = 'button button-primary';
    receptionButton.type = 'button';
    receptionButton.textContent = 'Open reception';
    receptionButton.addEventListener('click', openReception);
    overviewActions.appendChild(receptionButton);

    const communicationsButton = document.createElement('button');
    communicationsButton.className = 'button button-secondary';
    communicationsButton.type = 'button';
    communicationsButton.textContent = 'Communications';
    communicationsButton.addEventListener('click', openCommunications);
    overviewActions.appendChild(communicationsButton);

    const governanceButton = document.createElement('button');
    governanceButton.className = 'button button-secondary';
    governanceButton.type = 'button';
    governanceButton.textContent = 'Data and governance';
    governanceButton.addEventListener('click', openGovernance);
    overviewActions.appendChild(governanceButton);

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
    people.hidden = true;
    seating.hidden = true;
    reception.hidden = true;
    communications.hidden = true;
    governance.hidden = true;
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

  const clearCredential = () => {
    if (credentialClearTimer !== null) window.clearTimeout(credentialClearTimer);
    credentialClearTimer = null;
    credentialToken.value = '';
    credential.hidden = true;
  };

  const showCredential = (token) => {
    clearCredential();
    if (typeof token !== 'string' || token === '') return;
    credentialToken.value = token;
    credential.hidden = false;
    credentialToken.focus();
    credentialToken.select();
    credentialClearTimer = window.setTimeout(clearCredential, 300000);
  };

  const presentReturnedCredential = (result) => {
    const token = result?.payload?.data?.credential?.token;
    if (typeof token === 'string' && token !== '') {
      showCredential(token);
      peopleNotice.textContent = 'Credential returned once. Copy it securely, then clear this field.';
      return;
    }
    clearCredential();
    peopleNotice.textContent = 'The operation completed without returning a credential. Rotate the credential if a new delivery value is required.';
  };

  const selectPeopleTab = (name) => {
    peopleTabs.forEach((candidate) => {
      const tab = document.getElementById(`eventflow-${candidate}-tab`);
      const panel = document.getElementById(`eventflow-${candidate}-panel`);
      const selected = candidate === name;
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      tab.tabIndex = selected ? 0 : -1;
      panel.hidden = !selected;
    });
  };

  const actionButton = (label, handler, destructive = false) => {
    const button = document.createElement('button');
    button.className = destructive ? 'button button-link-delete' : 'button button-small';
    button.type = 'button';
    button.textContent = label;
    button.addEventListener('click', handler);
    return button;
  };

  const recordCard = (title, statusValue, facts) => {
    const card = document.createElement('article');
    card.className = 'eventflow-person-card';
    appendText(card, 'p', 'eventflow-event-card__status', statusValue);
    appendText(card, 'h4', 'eventflow-person-card__title', title);
    const description = document.createElement('p');
    description.className = 'eventflow-person-card__facts';
    description.textContent = facts.filter(Boolean).join(' • ');
    card.appendChild(description);
    const actions = document.createElement('div');
    actions.className = 'eventflow-person-card__actions';
    card.appendChild(actions);
    return { card, actions };
  };

  const runPeopleMutation = async (path, options = {}, confirmation = null) => {
    if (confirmation && !window.confirm(confirmation)) return null;
    peopleNotice.textContent = 'Saving change…';
    try {
      const result = await requestJson(path, {
        method: 'POST',
        ...options,
        headers: mutationHeaders(),
      });
      peopleNotice.textContent = 'Change accepted. Refreshing authoritative records…';
      return result;
    } catch (error) {
      const failure = error.code ? ` Failure: ${error.code}.` : '';
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      peopleNotice.textContent = `The latest state could not be confirmed. Refresh before retrying.${failure}${reference}`;
      return null;
    }
  };

  const renderMemberships = (memberships) => {
    membershipList.replaceChildren();
    if (!memberships.length) appendText(membershipList, 'p', 'eventflow-admin__status', 'No memberships found.');
    memberships.forEach((membership) => {
      const { card, actions } = recordCard(
        `WordPress user ${membership.user_id}`,
        String(membership.status || 'unknown'),
        [String(membership.role || ''), membership.is_primary_owner ? 'Primary owner' : '', membership.expires_at ? `Expires ${membership.expires_at}` : ''],
      );
      const eventId = encodeURIComponent(String(activeEvent.id));
      const membershipId = encodeURIComponent(String(membership.id));
      const transition = async (action, destructive = false) => {
        const result = await runPeopleMutation(
          `events/${eventId}/memberships/${membershipId}/${action}`,
          {},
          destructive ? `${action} this membership?` : null,
        );
        if (result) await loadPeopleData(false);
      };
      if (membership.status === 'active' && !membership.is_primary_owner) actions.appendChild(actionButton('Suspend', () => transition('suspend')));
      if (membership.status === 'suspended') actions.appendChild(actionButton('Reactivate', () => transition('reactivate')));
      if (!membership.is_primary_owner && membership.status !== 'revoked') actions.appendChild(actionButton('Revoke', () => transition('revoke', true), true));
      membershipList.appendChild(card);
    });
  };

  const populateInvitationOptions = (invitations) => {
    attendeeInvitation.replaceChildren();
    invitations.filter((invitation) => invitation.archived_at === null).forEach((invitation) => {
      const option = document.createElement('option');
      option.value = String(invitation.id);
      option.textContent = `${invitation.primary_name} (${invitation.code})`;
      attendeeInvitation.appendChild(option);
    });
  };

  const resetInvitationEditor = () => {
    editingInvitationId = null;
    editingInvitationEtag = null;
    invitationForm.reset();
    setField(invitationForm, 'capacity', 1);
    field(invitationForm, 'token_expires_at').disabled = false;
    invitationSubmit.textContent = 'Create invitation';
    invitationEditCancel.hidden = true;
  };

  const startInvitationEdit = async (invitationId) => {
    if (!activeEvent) return;
    peopleNotice.textContent = 'Loading current Invitation profile…';
    try {
      const eventId = encodeURIComponent(String(activeEvent.id));
      const result = await requestJson(`events/${eventId}/invitations/${encodeURIComponent(String(invitationId))}`);
      const invitation = result.payload.data || {};
      editingInvitationId = invitation.id;
      editingInvitationEtag = result.etag;
      ['primary_name', 'primary_email', 'primary_phone', 'capacity', 'organizer_notes']
        .forEach((name) => setField(invitationForm, name, invitation[name]));
      setField(invitationForm, 'token_expires_at', null);
      field(invitationForm, 'token_expires_at').disabled = true;
      invitationSubmit.textContent = 'Save invitation profile';
      invitationEditCancel.hidden = false;
      selectPeopleTab('invitations');
      field(invitationForm, 'primary_name').focus();
      peopleNotice.textContent = 'Editing the current revision. Credential expiry is changed only during credential rotation.';
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      peopleNotice.textContent = `Invitation profile unavailable.${reference}`;
    }
  };

  const invitationMatchesFilter = (invitation) => {
    const state = String(invitationStateFilter.value || 'all');
    const archived = invitation.archived_at !== null;
    if (state === 'active' && archived) return false;
    if (state === 'archived' && !archived) return false;
    const query = String(invitationFilter.value || '').trim().toLocaleLowerCase();
    if (query === '') return true;
    return [invitation.primary_name, invitation.primary_email, invitation.primary_phone, invitation.code]
      .some((value) => String(value || '').toLocaleLowerCase().includes(query));
  };

  const renderInvitations = (invitations = loadedInvitations) => {
    loadedInvitations = invitations;
    invitationList.replaceChildren();
    populateInvitationOptions(invitations);
    const visibleInvitations = invitations.filter(invitationMatchesFilter);
    invitationFilterStatus.textContent = `${visibleInvitations.length} of ${invitations.length} invitations shown.`;
    if (!visibleInvitations.length) appendText(invitationList, 'p', 'eventflow-admin__status', 'No invitations match the current filter.');
    visibleInvitations.forEach((invitation) => {
      const state = invitation.archived_at ? 'archived' : String(invitation.status || 'unknown');
      const { card, actions } = recordCard(
        String(invitation.primary_name || 'Unnamed invitation'),
        state,
        [String(invitation.code || ''), invitation.primary_email || '', invitation.primary_phone || '', `Capacity ${invitation.capacity}`, `RSVP ${invitation.response_status}`],
      );
      const eventId = encodeURIComponent(String(activeEvent.id));
      const invitationId = encodeURIComponent(String(invitation.id));
      const invitationPath = `events/${eventId}/invitations/${invitationId}`;
      actions.appendChild(actionButton('Edit profile', () => startInvitationEdit(invitation.id)));
      const invitationAction = async (action, returnsCredential = false, destructive = false) => {
        if (returnsCredential) clearCredential();
        const result = await runPeopleMutation(
          `${invitationPath}/${action}`,
          {},
          destructive ? `${action} this invitation?` : null,
        );
        if (!result) return;
        await loadPeopleData(false);
        if (returnsCredential) presentReturnedCredential(result);
      };
      if (invitation.archived_at) {
        actions.appendChild(actionButton('Restore', () => invitationAction('restore')));
      } else {
        actions.appendChild(actionButton('Archive', () => invitationAction('archive', false, true), true));
        if (invitation.status === 'active') {
          actions.appendChild(actionButton('Rotate credential', () => invitationAction('rotate-token', true, true)));
          actions.appendChild(actionButton('Revoke credential', () => invitationAction('revoke', false, true), true));
        } else if (invitation.status === 'revoked') {
          actions.appendChild(actionButton('Activate credential', () => invitationAction('activate', true)));
        }
      }
      invitationList.appendChild(card);
    });
  };

  const renderAttendees = (attendees) => {
    attendeeList.replaceChildren();
    if (!attendees.length) appendText(attendeeList, 'p', 'eventflow-admin__status', 'No attendees found.');
    attendees.forEach((attendee) => {
      const { card, actions } = recordCard(
        String(attendee.display_name || 'Unnamed attendee'),
        String(attendee.status || 'unknown'),
        [String(attendee.role || ''), `Invitation ${attendee.invitation_id}`, attendee.email || '', attendee.dietary_requirements ? 'Dietary needs recorded' : '', attendee.accessibility_requirements ? 'Accessibility needs recorded' : ''],
      );
      const eventId = encodeURIComponent(String(activeEvent.id));
      const attendeeId = encodeURIComponent(String(attendee.id));
      const attendeeAction = async (action, destructive = false) => {
        const result = await runPeopleMutation(
          `events/${eventId}/attendees/${attendeeId}/${action}`,
          { body: JSON.stringify({ invitation_id: Number(attendee.invitation_id) }) },
          destructive ? `${action} this attendee?` : null,
        );
        if (result) await loadPeopleData(false);
      };
      if (attendee.status === 'cancelled') actions.appendChild(actionButton('Restore', () => attendeeAction('restore')));
      else actions.appendChild(actionButton('Cancel', () => attendeeAction('cancel', true), true));
      attendeeList.appendChild(card);
    });
  };

  const loadPeopleData = async (clearReturnedCredential = true) => {
    if (!activeEvent) return;
    if (clearReturnedCredential) clearCredential();
    peopleNotice.textContent = 'Loading current people records…';
    [membershipForm, invitationForm, attendeeForm].forEach((form) => disableForm(form, true));
    const eventPath = `events/${encodeURIComponent(String(activeEvent.id))}`;
    const [memberships, invitations, attendees] = await Promise.allSettled([
      requestAllPages(`${eventPath}/memberships`, 'next_after_membership_id'),
      requestAllPages(`${eventPath}/invitations`, 'next_after_invitation_id'),
      requestAllPages(`${eventPath}/attendees`, 'next_after_attendee_id'),
    ]);
    const messages = [];
    if (memberships.status === 'fulfilled') {
      renderMemberships(Array.isArray(memberships.value.payload.data) ? memberships.value.payload.data : []);
      disableForm(membershipForm, false);
    } else {
      membershipList.replaceChildren();
      messages.push('Team access unavailable.');
    }
    if (invitations.status === 'fulfilled') {
      renderInvitations(Array.isArray(invitations.value.payload.data) ? invitations.value.payload.data : []);
      disableForm(invitationForm, false);
      field(invitationForm, 'token_expires_at').disabled = editingInvitationId !== null;
    } else {
      invitationList.replaceChildren();
      attendeeInvitation.replaceChildren();
      messages.push('Invitation access unavailable.');
    }
    if (attendees.status === 'fulfilled') {
      renderAttendees(Array.isArray(attendees.value.payload.data) ? attendees.value.payload.data : []);
      if (invitations.status === 'fulfilled' && attendeeInvitation.options.length > 0) disableForm(attendeeForm, false);
    } else {
      attendeeList.replaceChildren();
      messages.push('Attendee access unavailable.');
    }
    peopleNotice.textContent = messages.join(' ') || 'People records loaded.';
  };

  const openPeople = async () => {
    if (!activeEvent) return;
    resetInvitationEditor();
    setup.hidden = true;
    seating.hidden = true;
    reception.hidden = true;
    communications.hidden = true;
    governance.hidden = true;
    overviewFacts.hidden = true;
    people.hidden = false;
    selectPeopleTab('memberships');
    await loadPeopleData();
  };

  const submitMembership = async (submissionEvent) => {
    submissionEvent.preventDefault();
    if (!activeEvent) return;
    disableForm(membershipForm, true);
    const eventId = encodeURIComponent(String(activeEvent.id));
    const result = await runPeopleMutation(`events/${eventId}/memberships`, {
      body: JSON.stringify({
        user_id: Number(field(membershipForm, 'user_id').value),
        role: String(field(membershipForm, 'role').value),
        expires_at: nullableText(membershipForm, 'expires_at'),
      }),
    });
    if (!result) {
      disableForm(membershipForm, false);
      return;
    }
    membershipForm.reset();
    await loadPeopleData(false);
  };

  const submitInvitation = async (submissionEvent) => {
    submissionEvent.preventDefault();
    if (!activeEvent) return;
    if (editingInvitationId === null) clearCredential();
    disableForm(invitationForm, true);
    const eventId = encodeURIComponent(String(activeEvent.id));
    const profile = {
      primary_name: String(field(invitationForm, 'primary_name').value).trim(),
      primary_email: nullableText(invitationForm, 'primary_email'),
      primary_phone: nullableText(invitationForm, 'primary_phone'),
      capacity: Number(field(invitationForm, 'capacity').value),
    };
    let result;
    if (editingInvitationId !== null && editingInvitationEtag !== null) {
      result = await requestJson(`events/${eventId}/invitations/${encodeURIComponent(String(editingInvitationId))}`, {
        method: 'PATCH',
        headers: mutationHeaders(editingInvitationEtag),
        body: JSON.stringify({ ...profile, organizer_notes: nullableText(invitationForm, 'organizer_notes') }),
      }).catch((error) => {
        const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
        peopleNotice.textContent = `Invitation profile was not saved. Refresh before retrying.${reference}`;
        return null;
      });
    } else {
      result = await runPeopleMutation(`events/${eventId}/invitations`, {
        body: JSON.stringify({ ...profile, token_expires_at: nullableText(invitationForm, 'token_expires_at') }),
      });
    }
    if (!result) {
      disableForm(invitationForm, false);
      field(invitationForm, 'token_expires_at').disabled = editingInvitationId !== null;
      return;
    }
    const credentialResult = editingInvitationId === null ? result : null;
    resetInvitationEditor();
    await loadPeopleData(false);
    if (credentialResult) presentReturnedCredential(credentialResult);
  };

  const submitAttendee = async (submissionEvent) => {
    submissionEvent.preventDefault();
    if (!activeEvent) return;
    disableForm(attendeeForm, true);
    const eventId = encodeURIComponent(String(activeEvent.id));
    const result = await runPeopleMutation(`events/${eventId}/attendees`, {
      body: JSON.stringify({
        invitation_id: Number(field(attendeeForm, 'invitation_id').value),
        display_name: String(field(attendeeForm, 'display_name').value).trim(),
        role: String(field(attendeeForm, 'role').value),
        email: nullableText(attendeeForm, 'email'),
        phone: nullableText(attendeeForm, 'phone'),
        dietary_requirements: nullableText(attendeeForm, 'dietary_requirements'),
        accessibility_requirements: nullableText(attendeeForm, 'accessibility_requirements'),
      }),
    });
    if (!result) {
      disableForm(attendeeForm, false);
      return;
    }
    attendeeForm.reset();
    await loadPeopleData(false);
  };

  const seatingPath = () => `events/${encodeURIComponent(String(activeEvent.id))}`;

  const runSeatingMutation = async (path, body, etag = null) => {
    seatingNotice.textContent = 'Saving seating change…';
    try {
      const result = await requestJson(path, {
        method: 'POST',
        headers: mutationHeaders(etag),
        body: JSON.stringify(body),
      });
      seatingNotice.textContent = 'Change accepted. Refreshing the authoritative seating plan…';
      return result;
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      seatingNotice.textContent = `The seating change could not be confirmed. Refresh before retrying.${reference}`;
      return null;
    }
  };

  const option = (value, label) => {
    const element = document.createElement('option');
    element.value = String(value);
    element.textContent = String(label);
    return element;
  };

  const populatePlacementSeats = () => {
    placementSeat.replaceChildren(option('', 'Table assignment only'));
    const table = seatingTables.find((candidate) => String(candidate.id) === placementTable.value);
    (table?.seats || []).forEach((seat) => {
      placementSeat.appendChild(option(seat.id, `${seat.label}${seat.accessible ? ' — accessible' : ''}`));
    });
  };

  const renderSeatingTables = (tables) => {
    seatingTables = tables;
    tableList.replaceChildren();
    placementTable.replaceChildren();
    if (!tables.length) appendText(tableList, 'p', 'eventflow-admin__status', 'No tables configured.');
    tables.forEach((table) => {
      const { card } = recordCard(
        String(table.name || 'Unnamed table'),
        `${(table.seats || []).length} seat${(table.seats || []).length === 1 ? '' : 's'}`,
        [`Capacity ${table.capacity}`, `Revision ${table.revision}`],
      );
      const seatList = document.createElement('ul');
      seatList.className = 'eventflow-seat-list';
      (table.seats || []).forEach((seat) => {
        const item = document.createElement('li');
        item.textContent = `${seat.label}${seat.accessible ? ' — accessible seat' : ''}`;
        seatList.appendChild(item);
      });
      if (seatList.childElementCount) card.insertBefore(seatList, card.lastElementChild);
      tableList.appendChild(card);
      placementTable.appendChild(option(table.id, table.name));
    });
    populatePlacementSeats();
  };

  const renderSeatingGroups = (groups) => {
    groupList.replaceChildren();
    if (!groups.length) appendText(groupList, 'p', 'eventflow-admin__status', 'No seating groups configured.');
    groups.forEach((group) => {
      const { card } = recordCard(
        String(group.name || 'Unnamed group'),
        String(group.constraint_level || 'unknown'),
        [String(group.category || ''), `Priority ${group.priority}`, `Attendees ${(group.attendee_ids || []).join(', ')}`],
      );
      groupList.appendChild(card);
    });
  };

  const renderReadiness = (readiness) => {
    seatingReadiness.replaceChildren();
    const heading = document.createElement('h4');
    heading.textContent = readiness.ready ? 'Ready for assisted seating' : 'Seating setup needs attention';
    seatingReadiness.appendChild(heading);
    const messages = [...(readiness.errors || []), ...(readiness.warnings || [])];
    if (!messages.length) {
      appendText(seatingReadiness, 'p', 'eventflow-admin__status', 'Tables, attendees, and constraints passed the current readiness check.');
      return;
    }
    const listElement = document.createElement('ul');
    messages.forEach((message) => {
      const item = document.createElement('li');
      item.textContent = String(message);
      listElement.appendChild(item);
    });
    seatingReadiness.appendChild(listElement);
  };

  const renderRecommendation = (recommendation) => {
    seatingRecommendation = recommendation;
    recommendationResult.replaceChildren();
    if (!recommendation) return;
    appendText(recommendationResult, 'p', 'eventflow-event-card__status', String(recommendation.status || 'generated'));
    appendText(recommendationResult, 'h5', 'eventflow-person-card__title', `Recommendation ${recommendation.id}`);
    const listElement = document.createElement('ol');
    listElement.className = 'eventflow-recommendation-list';
    (recommendation.placements || []).forEach((placement) => {
      const item = document.createElement('li');
      item.textContent = `Attendee ${placement.attendee_id} → table ${placement.table_id}${placement.seat_id ? `, seat ${placement.seat_id}` : ''}${placement.reason ? ` — ${placement.reason}` : ''}`;
      listElement.appendChild(item);
    });
    recommendationResult.appendChild(listElement);
    (recommendation.warnings || []).forEach((warning) => appendText(recommendationResult, 'p', 'description', `Warning: ${warning}`));
    if (recommendation.status !== 'applied') {
      recommendationResult.appendChild(actionButton('Apply reviewed recommendation', applyRecommendation));
    }
  };

  const loadSeatingData = async () => {
    if (!activeEvent) return;
    seatingNotice.textContent = 'Loading tables, groups, attendees, and readiness…';
    [tableForm, groupForm, placementForm, recommendationForm].forEach((form) => disableForm(form, true));
    const eventPath = seatingPath();
    const [tablesResult, groupsResult, attendeesResult, readinessResult] = await Promise.allSettled([
      requestJson(`${eventPath}/tables`),
      requestJson(`${eventPath}/seating-groups`),
      requestJson(`${eventPath}/attendees?limit=100`),
      requestJson(`${eventPath}/seating/readiness`),
    ]);
    const messages = [];
    if (tablesResult.status === 'fulfilled') {
      const summaries = Array.isArray(tablesResult.value.payload.data) ? tablesResult.value.payload.data : [];
      const details = await Promise.allSettled(summaries.map((table) => requestJson(`${eventPath}/tables/${encodeURIComponent(String(table.id))}`)));
      renderSeatingTables(details.filter((detail) => detail.status === 'fulfilled').map((detail) => detail.value.payload.data));
      disableForm(tableForm, false);
    } else {
      tableList.replaceChildren();
      messages.push('Tables unavailable.');
    }
    if (groupsResult.status === 'fulfilled') {
      renderSeatingGroups(Array.isArray(groupsResult.value.payload.data) ? groupsResult.value.payload.data : []);
      disableForm(groupForm, false);
    } else {
      groupList.replaceChildren();
      messages.push('Groups unavailable.');
    }
    placementAttendee.replaceChildren();
    if (attendeesResult.status === 'fulfilled') {
      (attendeesResult.value.payload.data || []).filter((attendee) => attendee.status !== 'cancelled').forEach((attendee) => {
        placementAttendee.appendChild(option(attendee.id, attendee.display_name));
      });
    } else messages.push('Attendees unavailable.');
    if (readinessResult.status === 'fulfilled') {
      renderReadiness(readinessResult.value.payload.data || {});
      if (readinessResult.value.payload.data?.ready) disableForm(recommendationForm, false);
    } else {
      seatingReadiness.replaceChildren();
      messages.push('Readiness unavailable.');
    }
    if (placementAttendee.options.length && placementTable.options.length) disableForm(placementForm, false);
    seatingNotice.textContent = messages.join(' ') || 'Current seating plan loaded.';
  };

  const openSeating = async () => {
    if (!activeEvent) return;
    clearCredential();
    setup.hidden = true;
    people.hidden = true;
    reception.hidden = true;
    communications.hidden = true;
    governance.hidden = true;
    overviewFacts.hidden = true;
    seating.hidden = false;
    renderRecommendation(null);
    await loadSeatingData();
  };

  const submitTable = async (submissionEvent) => {
    submissionEvent.preventDefault();
    const labels = String(field(tableForm, 'seat_labels').value || '').split(',').map((label) => label.trim()).filter(Boolean);
    const result = await runSeatingMutation(`${seatingPath()}/tables`, {
      name: String(field(tableForm, 'name').value).trim(),
      capacity: Number(field(tableForm, 'capacity').value),
      seats: labels.map((label) => ({ label, accessible: false })),
    });
    if (result) {
      tableForm.reset();
      setField(tableForm, 'capacity', 8);
      await loadSeatingData();
    }
  };

  const submitGroup = async (submissionEvent) => {
    submissionEvent.preventDefault();
    const attendeeIds = String(field(groupForm, 'attendee_ids').value || '').split(',').map((id) => Number(id.trim())).filter((id) => Number.isInteger(id) && id > 0);
    if (!attendeeIds.length) {
      seatingNotice.textContent = 'Enter at least one valid attendee ID.';
      return;
    }
    const result = await runSeatingMutation(`${seatingPath()}/seating-groups`, {
      name: String(field(groupForm, 'name').value).trim(),
      category: String(field(groupForm, 'category').value).trim(),
      constraint_level: String(field(groupForm, 'constraint_level').value),
      priority: Number(field(groupForm, 'priority').value),
      attendee_ids: attendeeIds,
    });
    if (result) {
      groupForm.reset();
      setField(groupForm, 'priority', 100);
      await loadSeatingData();
    }
  };

  const submitPlacement = async (submissionEvent) => {
    submissionEvent.preventDefault();
    const attendeeId = Number(field(placementForm, 'attendee_id').value);
    const seatValue = String(field(placementForm, 'seat_id').value || '');
    const result = await runSeatingMutation(`${seatingPath()}/attendees/${encodeURIComponent(String(attendeeId))}/seating/move`, {
      table_id: Number(field(placementForm, 'table_id').value),
      seat_id: seatValue ? Number(seatValue) : null,
      expected_assignment_id: null,
      override_required_group: false,
      override_reason: null,
    });
    if (result) seatingNotice.textContent = `Attendee ${attendeeId} was placed by the authoritative seating service.`;
  };

  const submitRecommendation = async (submissionEvent) => {
    submissionEvent.preventDefault();
    const result = await runSeatingMutation(`${seatingPath()}/seating/recommendations`, {
      seed: String(field(recommendationForm, 'seed').value).trim(),
    });
    if (result) {
      renderRecommendation(result.payload.data || null);
      seatingNotice.textContent = 'Recommendation generated for review. It has not been applied.';
    }
  };

  const applyRecommendation = async () => {
    if (!seatingRecommendation || !window.confirm('Apply this reviewed recommendation to the Event seating plan?')) return;
    const result = await runSeatingMutation(`${seatingPath()}/seating/recommendations/${encodeURIComponent(String(seatingRecommendation.id))}/apply`, {});
    if (result) {
      renderRecommendation(result.payload.data || null);
      await loadSeatingData();
      seatingNotice.textContent = 'The reviewed seating recommendation was applied.';
    }
  };

  const receptionEventPath = () => `events/${encodeURIComponent(String(activeEvent.id))}`;

  const receptionContext = () => {
    const stationValue = String(field(receptionSearchForm, 'station_id').value || '');
    return {
      stationId: stationValue ? Number(stationValue) : null,
      notes: nullableText(receptionSearchForm, 'notes'),
    };
  };

  const updateReceptionSelection = () => {
    const selected = receptionResults.querySelectorAll('input[name="reception_attendee"]:checked');
    receptionBulk.hidden = receptionAttendees.length === 0;
    receptionSelection.textContent = `${selected.length} attendee${selected.length === 1 ? '' : 's'} selected.`;
    receptionBulkCheckIn.disabled = selected.length === 0;
  };

  const refreshReceptionSearch = async () => {
    const query = String(field(receptionSearchForm, 'q').value || '').trim();
    if (query.length < 2) return;
    receptionNotice.textContent = 'Searching local reception records…';
    disableForm(receptionSearchForm, true);
    try {
      const { payload } = await requestJson(`${receptionEventPath()}/reception/attendees?q=${encodeURIComponent(query)}&limit=50`);
      receptionAttendees = Array.isArray(payload.data) ? payload.data : [];
      renderReceptionResults(receptionAttendees);
      receptionNotice.textContent = `${receptionAttendees.length} matching attendee${receptionAttendees.length === 1 ? '' : 's'} found.`;
    } catch (error) {
      receptionAttendees = [];
      receptionResults.replaceChildren();
      appendText(receptionResults, 'h4', '', 'Search results');
      appendText(receptionResults, 'p', 'eventflow-admin__status', 'Reception search is temporarily unavailable. Try again without leaving this workspace.');
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      receptionNotice.textContent = `Search failed.${reference}`;
      updateReceptionSelection();
    } finally {
      disableForm(receptionSearchForm, false);
      field(receptionSearchForm, 'q').focus();
    }
  };

  const runReceptionMutation = async (path, body, pendingMessage) => {
    receptionNotice.textContent = pendingMessage;
    receptionResults.querySelectorAll('button, input').forEach((control) => { control.disabled = true; });
    receptionBulkCheckIn.disabled = true;
    try {
      const result = await requestJson(path, {
        method: 'POST',
        headers: mutationHeaders(),
        body: JSON.stringify(body),
      });
      receptionNotice.textContent = result.payload.meta?.replayed
        ? 'The protected operation was already recorded. Refreshing current reception state…'
        : 'Arrival state recorded. Refreshing current reception records…';
      await refreshReceptionSearch();
      return true;
    } catch (error) {
      const duplicate = error.code === 'attendee_already_checked_in' || error.code === 'checkin_already_reversed';
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      receptionNotice.textContent = duplicate
        ? `This arrival state was already recorded; no duplicate action was created. Search again to reconcile.${reference}`
        : `The arrival state could not be confirmed. Search again before retrying.${reference}`;
      renderReceptionResults(receptionAttendees);
      return false;
    }
  };

  const checkInAttendees = async (attendeeIds) => {
    if (!attendeeIds.length) return;
    const context = receptionContext();
    const bulk = attendeeIds.length > 1;
    const path = `${receptionEventPath()}/check-ins${bulk ? '/bulk' : ''}`;
    const body = bulk
      ? { attendee_ids: attendeeIds, station_id: context.stationId, method: 'search', notes: context.notes }
      : { attendee_id: attendeeIds[0], station_id: context.stationId, method: 'search', notes: context.notes };
    await runReceptionMutation(path, body, `Recording ${attendeeIds.length} arrival${attendeeIds.length === 1 ? '' : 's'}…`);
  };

  const reverseCheckIn = async (attendee, form) => {
    const reason = String(field(form, 'reason').value || '').trim();
    if (!reason) {
      receptionNotice.textContent = 'Enter a correction reason before reversing a check-in.';
      field(form, 'reason').focus();
      return;
    }
    await runReceptionMutation(
      `${receptionEventPath()}/check-ins/${encodeURIComponent(String(attendee.active_check_in_id))}/reverse`,
      { reason },
      `Reversing the recorded arrival for ${attendee.display_name}…`,
    );
  };

  const renderReceptionResults = (attendees) => {
    receptionResults.replaceChildren();
    appendText(receptionResults, 'h4', '', 'Search results');
    if (!attendees.length) {
      appendText(receptionResults, 'p', 'eventflow-admin__status', 'No matching attendees found. Try another name.');
      updateReceptionSelection();
      return;
    }
    attendees.forEach((attendee) => {
      const card = document.createElement('article');
      card.className = 'eventflow-reception-card';
      appendText(card, 'p', 'eventflow-event-card__status', attendee.checked_in ? 'Checked in' : String(attendee.attendance_status || 'unknown'));
      appendText(card, 'h5', 'eventflow-reception-card__name', String(attendee.display_name || 'Unnamed attendee'));
      appendText(card, 'p', 'eventflow-person-card__facts', attendee.table_name
        ? `Table ${attendee.table_name}${attendee.seat_label ? ` • Seat ${attendee.seat_label}` : ''}`
        : 'No table assigned');
      if (!attendee.checked_in && attendee.attendance_status === 'confirmed') {
        const selectionLabel = document.createElement('label');
        selectionLabel.className = 'eventflow-reception-card__select';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'reception_attendee';
        checkbox.value = String(attendee.id);
        checkbox.addEventListener('change', updateReceptionSelection);
        selectionLabel.append(checkbox, document.createTextNode(' Select for bulk check-in'));
        card.appendChild(selectionLabel);
        card.appendChild(actionButton('Check in now', () => checkInAttendees([Number(attendee.id)])));
      } else if (attendee.checked_in && attendee.active_check_in_id) {
        const form = document.createElement('form');
        form.className = 'eventflow-reception-card__reversal';
        const label = document.createElement('label');
        const inputId = `eventflow-reversal-reason-${attendee.id}`;
        label.htmlFor = inputId;
        label.textContent = 'Correction reason';
        const input = document.createElement('input');
        input.id = inputId;
        input.maxLength = 500;
        input.name = 'reason';
        input.required = true;
        input.type = 'text';
        const button = document.createElement('button');
        button.className = 'button button-secondary';
        button.type = 'submit';
        button.textContent = 'Reverse check-in';
        form.append(label, input, button);
        form.addEventListener('submit', (submissionEvent) => {
          submissionEvent.preventDefault();
          reverseCheckIn(attendee, form);
        });
        card.appendChild(form);
      } else {
        appendText(card, 'p', 'description', 'This attendee is not currently eligible for check-in.');
      }
      receptionResults.appendChild(card);
    });
    updateReceptionSelection();
  };

  const openReception = () => {
    if (!activeEvent) return;
    clearCredential();
    setup.hidden = true;
    people.hidden = true;
    seating.hidden = true;
    overviewFacts.hidden = true;
    reception.hidden = false;
    communications.hidden = true;
    governance.hidden = true;
    receptionAttendees = [];
    receptionSearchForm.reset();
    receptionResults.replaceChildren();
    appendText(receptionResults, 'h4', '', 'Search results');
    appendText(receptionResults, 'p', 'eventflow-admin__status', 'Search for a guest to begin reception.');
    receptionBulk.hidden = true;
    receptionNotice.textContent = 'Reception is ready. Search uses EventFlow records and does not depend on messaging providers.';
    field(receptionSearchForm, 'q').focus();
  };

  const communicationEventPath = () => `events/${encodeURIComponent(String(activeEvent.id))}`;

  const clearCommunicationDetails = () => {
    messageDetail.querySelectorAll('.eventflow-message-retry').forEach((button) => button.remove());
    templatePreviewSubject.textContent = '';
    templatePreviewBody.textContent = '';
    templatePreview.hidden = true;
    messageDetailTitle.textContent = 'Message detail';
    messageDetailRecipient.textContent = '';
    messageDetailContent.textContent = '';
    messageDetail.hidden = true;
  };

  const selectCommunicationTab = (name) => {
    clearCommunicationDetails();
    communicationTabs.forEach((candidate) => {
      const tab = document.getElementById(`eventflow-${candidate}-tab`);
      const panel = document.getElementById(`eventflow-${candidate}-panel`);
      const selected = candidate === name;
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      tab.tabIndex = selected ? 0 : -1;
      panel.hidden = !selected;
    });
  };

  const escapeInvitationHtml = (value) => String(value || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const validInvitationImageUrl = () => {
    const value = String(invitationImage.value || '').trim();
    if (!value) return '';
    try {
      const parsed = new URL(value, window.location.origin);
      return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '';
    } catch (error) {
      return '';
    }
  };

  const invitationMergeValues = (test = false) => ({
    recipient_name: test ? String(invitationTestName.value || 'Test Guest').trim() : '{{recipient_name}}',
    event_name: test ? String(activeEvent?.name || 'Your event') : '{{event_name}}',
    guest_link: test ? new URL('/confirm/', window.location.origin).href : '{{guest_link}}',
  });

  const replaceInvitationFields = (source, values) => Object.entries(values)
    .reduce((result, [name, value]) => result.replaceAll(`{{${name}}}`, value), String(source || ''));

  const invitationContent = (test = false) => {
    const channel = String(invitationChannel.value || 'email');
    const values = invitationMergeValues(test);
    const plain = replaceInvitationFields(invitationMessage.value, values).trim();
    if (channel === 'sms') return { content: plain, plainText: plain };
    let html = escapeInvitationHtml(plain).replaceAll('\r\n', '\n').replaceAll('\n', '<br>');
    const guestLink = escapeInvitationHtml(values.guest_link);
    html = html.replaceAll(escapeInvitationHtml(values.guest_link), `<a href="${guestLink}">Open your personalized invitation</a>`);
    const imageUrl = validInvitationImageUrl();
    if (imageUrl) html = `<p><a href="${guestLink}"><img alt="Invitation card" src="${escapeInvitationHtml(imageUrl)}" style="display:block;height:auto;max-width:100%;"></a></p>${html}`;
    return { content: html, plainText: plain };
  };

  const phoneRegion = (phone) => {
    const normalized = String(phone || '').replace(/[^0-9+]/g, '');
    if (normalized.startsWith('+1') || (/^1?[2-9][0-9]{9}$/.test(normalized))) return 'north_america';
    return normalized ? 'international' : 'unknown';
  };

  const invitationRecipientAddress = (invitation) => String(invitationChannel.value) === 'email'
    ? String(invitation.primary_email || '').trim()
    : String(invitation.primary_phone || '').trim();

  const visibleInvitationRecipients = () => {
    const query = String(invitationRecipientSearch.value || '').trim().toLocaleLowerCase();
    const regionFilter = String(invitationPhoneRegion.value || 'all');
    return invitationRecipients.filter((invitation) => {
      if (invitation.archived_at !== null || invitation.status !== 'active' || !invitationRecipientAddress(invitation)) return false;
      if (String(invitationChannel.value) === 'sms' && regionFilter !== 'all' && phoneRegion(invitation.primary_phone) !== regionFilter) return false;
      if (!query) return true;
      return [invitation.primary_name, invitation.primary_email, invitation.primary_phone, invitation.code]
        .some((value) => String(value || '').toLocaleLowerCase().includes(query));
    });
  };

  const invalidatePreparedInvitation = () => {
    preparedInvitationCampaign = null;
    invitationSend.disabled = true;
    invitationReviewStatus.textContent = selectedInvitationRecipients.size
      ? 'Recipients or message changed. Review again before sending.'
      : 'Review remains disabled until at least one eligible recipient is selected.';
  };

  const updateInvitationSelection = () => {
    const eligibleIds = new Set(invitationRecipients.filter((invitation) => invitationRecipientAddress(invitation) && invitation.archived_at === null && invitation.status === 'active').map((invitation) => Number(invitation.id)));
    selectedInvitationRecipients = new Set(Array.from(selectedInvitationRecipients).filter((id) => eligibleIds.has(id)));
    const visible = visibleInvitationRecipients();
    invitationSelectionStatus.textContent = `${selectedInvitationRecipients.size} selected • ${visible.length} eligible contacts shown.`;
    invitationReview.disabled = selectedInvitationRecipients.size < 1;
  };

  const renderInvitationRecipients = () => {
    invitationRecipientList.replaceChildren();
    const visible = visibleInvitationRecipients();
    if (!visible.length) appendText(invitationRecipientList, 'p', 'eventflow-admin__status', 'No eligible contacts match these filters.');
    visible.forEach((invitation) => {
      const row = document.createElement('label');
      row.className = 'eventflow-invitation-recipient';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.value = String(invitation.id);
      checkbox.checked = selectedInvitationRecipients.has(Number(invitation.id));
      checkbox.addEventListener('change', () => {
        if (checkbox.checked) selectedInvitationRecipients.add(Number(invitation.id));
        else selectedInvitationRecipients.delete(Number(invitation.id));
        invalidatePreparedInvitation();
        updateInvitationSelection();
      });
      const name = document.createElement('span');
      name.className = 'eventflow-invitation-recipient__name';
      name.textContent = String(invitation.primary_name || 'Unnamed contact');
      const address = document.createElement('span');
      address.className = 'eventflow-invitation-recipient__address';
      address.textContent = invitationRecipientAddress(invitation);
      row.append(checkbox, name, address);
      invitationRecipientList.appendChild(row);
    });
    updateInvitationSelection();
  };

  const configureInvitationChannel = () => {
    const email = String(invitationChannel.value) === 'email';
    document.querySelectorAll('.eventflow-invitation-email-field').forEach((element) => { element.hidden = !email; });
    invitationSubject.required = email;
    invitationTestAddress.type = email ? 'email' : 'tel';
    invitationTestAddress.placeholder = email ? 'name@example.com' : '+15878910335';
    invitationTestAddress.previousElementSibling.textContent = email ? 'Your email address' : 'Your mobile number';
    invitationTestSend.textContent = email ? 'Send test email' : 'Send test SMS';
    invitationPhoneRegion.disabled = email;
    invitationMessage.maxLength = email ? 500000 : 1600;
    selectedInvitationRecipients.clear();
    invalidatePreparedInvitation();
    renderInvitationRecipients();
  };

  const sendInvitationTest = async () => {
    const channel = String(invitationChannel.value || 'email');
    const address = String(invitationTestAddress.value || '').trim();
    if (!address || !String(invitationMessage.value || '').trim() || (channel === 'email' && !String(invitationSubject.value || '').trim())) {
      invitationTestStatus.textContent = 'Enter your test address and message first.';
      invitationTestAddress.focus();
      return;
    }
    if (!window.confirm(`Send one test ${channel.toUpperCase()} to ${address}?`)) return;
    const rendered = invitationContent(true);
    invitationTestSend.disabled = true;
    invitationTestStatus.textContent = `Queueing one test ${channel.toUpperCase()}…`;
    try {
      const { payload } = await requestJson(`${communicationEventPath()}/messages/test`, {
        method: 'POST',
        headers: mutationHeaders(),
        body: JSON.stringify({
          channel,
          recipient_name: String(invitationTestName.value || 'Test Guest').trim(),
          recipient_address: address,
          subject: channel === 'email' ? String(invitationSubject.value || '').replaceAll('{{event_name}}', String(activeEvent?.name || 'Your event')).trim() : null,
          content: rendered.content,
          plain_text: rendered.plainText,
        }),
      });
      invitationTestStatus.textContent = `Test ${channel.toUpperCase()} queued successfully as message ${payload.data?.id || ''}. Check your inbox or phone.`;
      await loadMessages();
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      invitationTestStatus.textContent = `The test could not be queued. No guest-list messages were sent.${reference}`;
    } finally {
      invitationTestSend.disabled = false;
    }
  };

  const reviewInvitationCampaign = async () => {
    const ids = Array.from(selectedInvitationRecipients).sort((a, b) => a - b);
    const channel = String(invitationChannel.value || 'email');
    if (!ids.length) return;
    if (!String(invitationMessage.value || '').trim() || (channel === 'email' && !String(invitationSubject.value || '').trim())) {
      invitationReviewStatus.textContent = 'Complete the invitation message before reviewing recipients.';
      invitationMessage.focus();
      return;
    }
    if (String(invitationImage.value || '').trim() && !validInvitationImageUrl()) {
      invitationReviewStatus.textContent = 'Choose a valid public invitation-card image URL before reviewing.';
      invitationImage.focus();
      return;
    }
    invitationReview.disabled = true;
    invitationSend.disabled = true;
    invitationReviewStatus.textContent = 'Preparing a protected invitation campaign and checking the selected audience…';
    try {
      const stamp = new Date().toISOString().replace(/[^0-9]/g, '').slice(0, 14);
      const random = idempotencyKey().slice(-8);
      const rendered = invitationContent(false);
      const template = await requestJson(`${communicationEventPath()}/communication-templates`, {
        method: 'POST', headers: mutationHeaders(), body: JSON.stringify({
          key: `organizer.invitation.${channel}.${stamp}.${random}`,
          name: `Official invitation ${channel.toUpperCase()} ${stamp}`,
          channel, type: 'invitation',
          subject: channel === 'email' ? String(invitationSubject.value || '').trim() : null,
          body: rendered.content, plain_text: rendered.plainText,
          allowed_fields: ['recipient_name', 'event_name', 'guest_link'],
        }),
      });
      const templateId = Number(template.payload.data?.id);
      await requestJson(`${communicationEventPath()}/communication-templates/${encodeURIComponent(String(templateId))}/publish`, { method: 'POST', headers: mutationHeaders(), body: JSON.stringify({}) });
      const campaign = await requestJson(`${communicationEventPath()}/campaigns`, {
        method: 'POST', headers: mutationHeaders(), body: JSON.stringify({
          template_id: templateId,
          name: `Invitation ${channel.toUpperCase()} ${stamp}`,
          channel, purpose: 'invitation', audience_mode: 'snapshot',
          audience: { filter: 'active_invitations', invitation_ids: ids },
        }),
      });
      const campaignId = Number(campaign.payload.data?.id);
      const preview = await requestJson(`${communicationEventPath()}/campaigns/${encodeURIComponent(String(campaignId))}/audience-preview`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({}) });
      const count = Number(preview.payload.data?.recipient_count || 0);
      preparedInvitationCampaign = { id: campaignId, count };
      invitationSend.disabled = count < 1;
      invitationReviewStatus.textContent = count === ids.length
        ? `${count} recipients verified. Nothing has been sent yet.`
        : `${count} of ${ids.length} selected contacts are currently deliverable. Nothing has been sent yet.`;
      await loadCommunicationData();
    } catch (error) {
      preparedInvitationCampaign = null;
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      invitationReviewStatus.textContent = `Recipient review failed. Nothing was sent.${reference}`;
    } finally {
      invitationReview.disabled = selectedInvitationRecipients.size < 1;
    }
  };

  const sendPreparedInvitations = async () => {
    if (!preparedInvitationCampaign || preparedInvitationCampaign.count < 1) return;
    const { id, count } = preparedInvitationCampaign;
    const channel = String(invitationChannel.value || 'email').toUpperCase();
    if (!window.confirm(`Send this personalized ${channel} invitation to ${count} reviewed recipients now?`)) return;
    invitationSend.disabled = true;
    invitationReview.disabled = true;
    invitationReviewStatus.textContent = `Queueing ${count} personalized ${channel} invitations…`;
    try {
      const { payload } = await requestJson(`${communicationEventPath()}/campaigns/${encodeURIComponent(String(id))}/queue`, { method: 'POST', headers: mutationHeaders(), body: JSON.stringify({}) });
      const queued = Number(payload.data?.recipient_count || count);
      invitationReviewStatus.textContent = `${queued} personalized ${channel} invitations were queued. Delivery status appears in Advanced communication records.`;
      selectedInvitationRecipients.clear();
      preparedInvitationCampaign = null;
      renderInvitationRecipients();
      await loadCommunicationData();
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      invitationReviewStatus.textContent = `Sending could not be confirmed. Refresh message status before retrying.${reference}`;
    } finally {
      invitationReview.disabled = selectedInvitationRecipients.size < 1;
    }
  };

  const startInvitationTemplate = (channel) => {
    selectCommunicationTab('templates');
    const suffix = new Date().toISOString().slice(0, 10).replaceAll('-', '');
    field(templateForm, 'key').value = `invitation.${channel}.${suffix}`;
    field(templateForm, 'name').value = channel === 'email' ? 'Official invitation email' : 'Official invitation SMS';
    field(templateForm, 'channel').value = channel;
    field(templateForm, 'type').value = 'invitation';
    field(templateForm, 'allowed_fields').value = 'recipient_name, event_name, guest_link';
    field(templateForm, 'subject').value = channel === 'email' ? "You're invited to {{event_name}}" : '';
    const message = channel === 'email'
      ? 'Hello {{recipient_name}},\n\nYou are warmly invited to {{event_name}}. Please open your personalized invitation, confirm your attendance, and add your companions for seating:\n\n{{guest_link}}'
      : 'Hello {{recipient_name}}, you are invited to {{event_name}}. RSVP and add your companions: {{guest_link}}';
    field(templateForm, 'body').value = message;
    field(templateForm, 'plain_text').value = message;
    communicationsNotice.textContent = `A friendly ${channel.toUpperCase()} invitation draft is ready. Review it, save it, then publish it before creating a bulk campaign.`;
    field(templateForm, 'name').focus();
  };

  const communicationMutation = async (path, body, etag = null, method = 'POST') => {
    communicationsNotice.textContent = 'Saving communication change…';
    try {
      const result = await requestJson(path, {
        method,
        headers: mutationHeaders(etag),
        body: JSON.stringify(body),
      });
      communicationsNotice.textContent = result.payload.meta?.replayed
        ? 'This protected operation was already accepted. Refreshing authoritative records…'
        : 'Communication change accepted. Refreshing authoritative records…';
      return result;
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      communicationsNotice.textContent = `The communication state could not be confirmed. Refresh before retrying.${reference}`;
      return null;
    }
  };

  const templateAction = async (template, action) => {
    const path = `${communicationEventPath()}/communication-templates/${encodeURIComponent(String(template.id))}`;
    try {
      const current = await requestJson(path);
      const result = await communicationMutation(`${path}/${action}`, {}, current.etag);
      if (result) await loadCommunicationData();
    } catch (error) {
      communicationsNotice.textContent = 'The latest Template revision could not be loaded. Refresh before retrying.';
    }
  };

  const previewTemplate = async (template) => {
    clearCommunicationDetails();
    const values = {};
    (template.allowed_fields || []).forEach((name) => { values[name] = `[${name}]`; });
    communicationsNotice.textContent = 'Rendering Template preview…';
    try {
      const { payload } = await requestJson(`${communicationEventPath()}/communication-templates/${encodeURIComponent(String(template.id))}/preview`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ values }),
      });
      templatePreviewSubject.textContent = payload.data?.subject || 'No subject';
      templatePreviewBody.textContent = payload.data?.plain_text || payload.data?.body || '';
      templatePreview.hidden = false;
      communicationsNotice.textContent = 'Rendered preview loaded as text. No message was sent.';
      templatePreview.scrollIntoView({ block: 'nearest' });
    } catch (error) {
      communicationsNotice.textContent = 'Template preview unavailable. No message was sent.';
    }
  };

  const renderTemplates = (templates) => {
    communicationTemplates = templates;
    templateList.replaceChildren();
    campaignTemplate.replaceChildren();
    templates.filter((template) => template.status === 'published').forEach((template) => {
      campaignTemplate.appendChild(option(template.id, `${template.name} v${template.version}`));
    });
    if (!templates.length) appendText(templateList, 'p', 'eventflow-admin__status', 'No communication Templates found.');
    templates.forEach((template) => {
      const { card, actions } = recordCard(
        `${template.name} v${template.version}`,
        String(template.status || 'unknown'),
        [String(template.channel || ''), String(template.type || ''), `Revision ${template.revision}`],
      );
      actions.appendChild(actionButton('Preview', () => previewTemplate(template)));
      if (template.status === 'draft') actions.appendChild(actionButton('Publish', () => templateAction(template, 'publish')));
      if (template.status === 'published') actions.appendChild(actionButton('Create new version', () => templateAction(template, 'new-version')));
      if (template.status !== 'archived') actions.appendChild(actionButton('Archive', () => {
        if (window.confirm('Archive this Template version?')) templateAction(template, 'archive');
      }, true));
      templateList.appendChild(card);
    });
  };

  const campaignCommand = async (campaign, action, body = {}, confirmation = null) => {
    if (confirmation && !window.confirm(confirmation)) return;
    const path = `${communicationEventPath()}/campaigns/${encodeURIComponent(String(campaign.id))}`;
    try {
      const current = await requestJson(path);
      const result = await communicationMutation(`${path}/${action}`, body, action === 'queue' ? null : current.etag);
      if (result) await loadCommunicationData();
    } catch (error) {
      communicationsNotice.textContent = 'The latest Campaign revision could not be loaded. Refresh before retrying.';
    }
  };

  const renderCampaigns = (campaigns) => {
    campaignList.replaceChildren();
    if (!campaigns.length) appendText(campaignList, 'p', 'eventflow-admin__status', 'No Campaigns found.');
    campaigns.forEach((campaign) => {
      const { card, actions } = recordCard(
        String(campaign.name || 'Unnamed campaign'),
        String(campaign.status || 'unknown'),
        [String(campaign.channel || ''), String(campaign.purpose || ''), `Template ${campaign.template_id}`, campaign.recipient_count === null ? '' : `${campaign.recipient_count} recipients`],
      );
      if (campaign.status === 'draft') {
        const previewStatus = document.createElement('p');
        previewStatus.className = 'description';
        previewStatus.textContent = 'Review the current audience before scheduling or sending.';
        const scheduleForm = document.createElement('form');
        scheduleForm.className = 'eventflow-campaign-actions';
        const scheduleLabel = document.createElement('label');
        const scheduleId = `eventflow-campaign-schedule-${campaign.id}`;
        scheduleLabel.htmlFor = scheduleId;
        scheduleLabel.textContent = 'Schedule at (ISO 8601)';
        const scheduleInput = document.createElement('input');
        scheduleInput.id = scheduleId;
        scheduleInput.placeholder = '2026-09-01T18:00:00-06:00';
        scheduleInput.required = true;
        scheduleInput.type = 'text';
        const scheduleButton = document.createElement('button');
        scheduleButton.className = 'button button-secondary';
        scheduleButton.disabled = true;
        scheduleButton.type = 'submit';
        scheduleButton.textContent = 'Schedule';
        let reviewedRecipientCount = 0;
        const sendButton = actionButton(`Send bulk ${String(campaign.channel || 'message').toUpperCase()} now`, () => campaignCommand(campaign, 'queue', {}, `Send this personalized ${String(campaign.channel || 'message').toUpperCase()} to ${reviewedRecipientCount} reviewed recipients now?`));
        sendButton.disabled = true;
        scheduleForm.append(scheduleLabel, scheduleInput, scheduleButton);
        scheduleForm.addEventListener('submit', (submissionEvent) => {
          submissionEvent.preventDefault();
          campaignCommand(campaign, 'schedule', { scheduled_at: scheduleInput.value.trim() }, 'Schedule this Campaign for the reviewed audience?');
        });
        const previewButton = actionButton('Preview audience', async () => {
          communicationsNotice.textContent = 'Calculating the current Campaign audience…';
          try {
            const { payload } = await requestJson(`${communicationEventPath()}/campaigns/${encodeURIComponent(String(campaign.id))}/audience-preview`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({}),
            });
            reviewedRecipientCount = Number(payload.data?.recipient_count || 0);
            previewStatus.textContent = `${reviewedRecipientCount} recipients in the reviewed audience. Scheduling and sending are now enabled for this view.`;
            scheduleButton.disabled = false;
            sendButton.disabled = reviewedRecipientCount < 1;
            communicationsNotice.textContent = 'Audience preview complete. Review the recipient count before continuing.';
          } catch (error) {
            previewStatus.textContent = 'Audience preview unavailable. Scheduling and queueing remain disabled.';
            communicationsNotice.textContent = 'Audience preview failed. No messages were scheduled or sent.';
          }
        });
        actions.append(previewButton, sendButton);
        card.insertBefore(previewStatus, actions);
        card.appendChild(scheduleForm);
      }
      if (campaign.status === 'scheduled') actions.appendChild(actionButton('Cancel schedule', () => campaignCommand(campaign, 'cancel', {}, 'Cancel this scheduled Campaign?'), true));
      campaignList.appendChild(card);
    });
  };

  const showMessageDetail = async (message) => {
    clearCommunicationDetails();
    communicationsNotice.textContent = 'Loading protected Message detail…';
    try {
      const { payload, etag } = await requestJson(`${communicationEventPath()}/messages/${encodeURIComponent(String(message.id))}`);
      const detail = payload.data || {};
      messageDetailTitle.textContent = detail.subject || `Message ${detail.id}`;
      messageDetailRecipient.textContent = `${detail.recipient_name || 'Recipient'} — ${detail.recipient_address || ''}`;
      messageDetailContent.textContent = detail.plain_text || detail.content || '';
      messageDetail.hidden = false;
      communicationsNotice.textContent = 'Protected Message detail loaded.';
      if (detail.status === 'failed') {
        const retry = actionButton('Retry failed message', async () => {
          const result = await communicationMutation(`${communicationEventPath()}/messages/${encodeURIComponent(String(detail.id))}/retry`, {}, etag);
          if (result) {
            clearCommunicationDetails();
            await loadMessages();
          }
        });
        retry.classList.add('eventflow-message-retry');
        messageDetail.appendChild(retry);
      }
    } catch (error) {
      clearCommunicationDetails();
      communicationsNotice.textContent = 'Message detail unavailable.';
    }
  };

  const renderMessages = (messages) => {
    messageList.replaceChildren();
    if (!messages.length) appendText(messageList, 'p', 'eventflow-admin__status', 'No Messages match this filter.');
    messages.forEach((message) => {
      const { card, actions } = recordCard(
        message.subject || `Message ${message.id}`,
        String(message.status || 'unknown'),
        [message.recipient_name || '', String(message.channel || ''), `Attempts ${message.attempt_count || 0}`],
      );
      actions.appendChild(actionButton('View detail', () => showMessageDetail(message)));
      messageList.appendChild(card);
    });
  };

  const loadMessages = async () => {
    const campaignId = nullableText(messageFilterForm, 'campaign_id');
    const messageStatus = nullableText(messageFilterForm, 'status');
    const messageQuery = ['limit=100'];
    if (campaignId) messageQuery.push(`campaign_id=${encodeURIComponent(campaignId)}`);
    if (messageStatus) messageQuery.push(`status=${encodeURIComponent(messageStatus)}`);
    const { payload } = await requestJson(`${communicationEventPath()}/messages?${messageQuery.join('&')}`);
    renderMessages(Array.isArray(payload.data) ? payload.data : []);
  };

  const loadCommunicationData = async () => {
    if (!activeEvent) return;
    clearCommunicationDetails();
    communicationsNotice.textContent = 'Loading Templates, Campaigns, and Messages…';
    const [templates, campaigns, messages] = await Promise.allSettled([
      requestJson(`${communicationEventPath()}/communication-templates?limit=100`),
      requestJson(`${communicationEventPath()}/campaigns?limit=100`),
      requestJson(`${communicationEventPath()}/messages?limit=100`),
    ]);
    const failures = [];
    if (templates.status === 'fulfilled') renderTemplates(templates.value.payload.data || []);
    else { templateList.replaceChildren(); failures.push('Templates unavailable.'); }
    if (campaigns.status === 'fulfilled') renderCampaigns(campaigns.value.payload.data || []);
    else { campaignList.replaceChildren(); failures.push('Campaigns unavailable.'); }
    if (messages.status === 'fulfilled') renderMessages(messages.value.payload.data || []);
    else { messageList.replaceChildren(); failures.push('Messages unavailable.'); }
    disableForm(templateForm, templates.status !== 'fulfilled');
    disableForm(campaignForm, templates.status !== 'fulfilled' || campaigns.status !== 'fulfilled' || campaignTemplate.options.length === 0);
    disableForm(messageFilterForm, messages.status !== 'fulfilled');
    communicationsNotice.textContent = failures.join(' ') || 'Communication records loaded.';
  };

  const openCommunications = async () => {
    if (!activeEvent) return;
    clearCredential();
    setup.hidden = true;
    people.hidden = true;
    seating.hidden = true;
    reception.hidden = true;
    overviewFacts.hidden = true;
    communications.hidden = false;
    governance.hidden = true;
    selectCommunicationTab('templates');
    selectedInvitationRecipients.clear();
    preparedInvitationCampaign = null;
    invitationRecipientSearch.value = '';
    invitationPhoneRegion.value = 'all';
    invitationSend.disabled = true;
    const invitationRequest = requestAllPages(`${communicationEventPath()}/invitations`, 'next_after_invitation_id');
    const [, recipients] = await Promise.allSettled([loadCommunicationData(), invitationRequest]);
    if (recipients.status === 'fulfilled') {
      invitationRecipients = Array.isArray(recipients.value.payload.data) ? recipients.value.payload.data : [];
      configureInvitationChannel();
    } else {
      invitationRecipients = [];
      renderInvitationRecipients();
      communicationsNotice.textContent = `${communicationsNotice.textContent} Guest contacts unavailable.`.trim();
    }
  };

  const submitTemplate = async (submissionEvent) => {
    submissionEvent.preventDefault();
    const fields = String(field(templateForm, 'allowed_fields').value || '').split(',').map((value) => value.trim()).filter(Boolean);
    const result = await communicationMutation(`${communicationEventPath()}/communication-templates`, {
      key: String(field(templateForm, 'key').value).trim(),
      name: String(field(templateForm, 'name').value).trim(),
      channel: String(field(templateForm, 'channel').value),
      type: String(field(templateForm, 'type').value).trim(),
      subject: nullableText(templateForm, 'subject'),
      body: String(field(templateForm, 'body').value),
      plain_text: nullableText(templateForm, 'plain_text'),
      allowed_fields: fields,
    });
    if (result) { templateForm.reset(); await loadCommunicationData(); }
  };

  const submitCampaign = async (submissionEvent) => {
    submissionEvent.preventDefault();
    const ids = String(field(campaignForm, 'invitation_ids').value || '').split(',').map((value) => Number(value.trim())).filter((value) => Number.isInteger(value) && value > 0);
    const result = await communicationMutation(`${communicationEventPath()}/campaigns`, {
      template_id: Number(field(campaignForm, 'template_id').value),
      name: String(field(campaignForm, 'name').value).trim(),
      channel: String(field(campaignForm, 'channel').value),
      purpose: String(field(campaignForm, 'purpose').value),
      audience_mode: String(field(campaignForm, 'audience_mode').value),
      audience: { filter: String(field(campaignForm, 'filter').value).trim(), invitation_ids: ids },
    });
    if (result) { campaignForm.reset(); await loadCommunicationData(); }
  };

  const governanceEventPath = () => `events/${encodeURIComponent(String(activeEvent.id))}`;

  const clearGovernanceDetails = () => {
    importDetailContent.textContent = '';
    importDetail.hidden = true;
    auditDetailContent.textContent = '';
    auditDetail.hidden = true;
    diagnosticsContent.textContent = '';
    diagnosticsDetail.hidden = true;
    auditIntegrityResult.replaceChildren();
  };

  const selectGovernanceTab = (name) => {
    clearGovernanceDetails();
    governanceTabs.forEach((candidate) => {
      const tab = document.getElementById(`eventflow-${candidate}-tab`);
      const panel = document.getElementById(`eventflow-${candidate}-panel`);
      const selected = candidate === name;
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      tab.tabIndex = selected ? 0 : -1;
      panel.hidden = !selected;
    });
  };

  const governanceMutation = async (path, body, etag = null, method = 'POST') => {
    governanceNotice.textContent = 'Saving privileged change…';
    try {
      const result = await requestJson(path, { method, headers: mutationHeaders(etag), body: JSON.stringify(body) });
      governanceNotice.textContent = result.payload.meta?.replayed
        ? 'This protected operation was already accepted. Refreshing authoritative records…'
        : 'Privileged change accepted. Refreshing authoritative records…';
      return result;
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      governanceNotice.textContent = `The privileged change could not be confirmed. Refresh before retrying.${reference}`;
      return null;
    }
  };

  const loadImportRows = async (job) => {
    clearGovernanceDetails();
    governanceNotice.textContent = 'Loading bounded Import rows…';
    try {
      const { payload } = await requestJson(`${governanceEventPath()}/imports/${encodeURIComponent(String(job.id))}/rows?limit=100`);
      importDetailContent.textContent = JSON.stringify(payload.data || [], null, 2);
      importDetail.hidden = false;
      governanceNotice.textContent = 'Import row review loaded. Raw and normalized values remain protected in this view.';
    } catch (error) {
      governanceNotice.textContent = 'Import rows unavailable.';
    }
  };

  const importTransition = async (job, action) => {
    if (action === 'apply' && !window.confirm('Apply this validated Import to the Event?')) return;
    if (action === 'cancel' && !window.confirm('Cancel this Import job?')) return;
    const path = `${governanceEventPath()}/imports/${encodeURIComponent(String(job.id))}`;
    try {
      const current = await requestJson(path);
      const result = await governanceMutation(`${path}/${action}`, {}, current.etag);
      if (result) await loadGovernanceData();
    } catch (error) {
      governanceNotice.textContent = 'The latest Import revision could not be loaded. Refresh before retrying.';
    }
  };

  const validateImportMapping = async (job, form) => {
    const mapping = { primary_name: String(field(form, 'primary_name').value).trim() };
    ['primary_email', 'primary_phone', 'capacity'].forEach((name) => {
      const source = String(field(form, name).value || '').trim();
      if (source) mapping[name] = source;
    });
    const path = `${governanceEventPath()}/imports/${encodeURIComponent(String(job.id))}`;
    try {
      const current = await requestJson(path);
      const result = await governanceMutation(`${path}/validate`, { mapping }, current.etag);
      if (result) await loadGovernanceData();
    } catch (error) {
      governanceNotice.textContent = 'The latest Import revision could not be loaded. Refresh before validating.';
    }
  };

  const renderImports = (jobs) => {
    importList.replaceChildren();
    if (!jobs.length) appendText(importList, 'p', 'eventflow-admin__status', 'No Import jobs found.');
    jobs.forEach((job) => {
      const { card, actions } = recordCard(
        String(job.source_filename || `Import ${job.id}`),
        String(job.status || 'unknown'),
        [`Rows ${job.total_rows}`, `Valid ${job.valid_rows}`, `Warnings ${job.warning_rows}`, `Invalid ${job.invalid_rows}`, `Revision ${job.revision}`],
      );
      actions.appendChild(actionButton('Review rows', () => loadImportRows(job)));
      if (job.status === 'uploaded' || job.status === 'staged') {
        const mappingForm = document.createElement('form');
        mappingForm.className = 'eventflow-import-mapping';
        ['primary_name', 'primary_email', 'primary_phone', 'capacity'].forEach((name) => {
          const label = document.createElement('label');
          const id = `eventflow-import-${job.id}-${name}`;
          label.htmlFor = id;
          label.textContent = `${name.replaceAll('_', ' ')} column`;
          const input = document.createElement('input');
          input.id = id;
          input.name = name;
          input.required = name === 'primary_name';
          input.type = 'text';
          mappingForm.append(label, input);
        });
        const validateButton = document.createElement('button');
        validateButton.className = 'button button-secondary';
        validateButton.type = 'submit';
        validateButton.textContent = 'Validate mapping';
        mappingForm.appendChild(validateButton);
        mappingForm.addEventListener('submit', (submissionEvent) => { submissionEvent.preventDefault(); validateImportMapping(job, mappingForm); });
        card.appendChild(mappingForm);
      }
      if (job.status === 'validated') {
        actions.appendChild(actionButton('Dry-run result', async () => {
          try {
            const { payload } = await requestJson(`${governanceEventPath()}/imports/${encodeURIComponent(String(job.id))}/dry-run`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({}) });
            clearGovernanceDetails();
            importDetailContent.textContent = JSON.stringify(payload.data || {}, null, 2);
            importDetail.hidden = false;
            governanceNotice.textContent = 'Dry-run result loaded. No records were applied.';
          } catch (error) { governanceNotice.textContent = 'Dry-run result unavailable. No records were applied.'; }
        }));
        actions.appendChild(actionButton('Apply import', () => importTransition(job, 'apply')));
      }
      if (!['completed', 'cancelled'].includes(job.status)) actions.appendChild(actionButton('Cancel', () => importTransition(job, 'cancel'), true));
      importList.appendChild(card);
    });
  };

  const downloadExport = async (record) => {
    governanceNotice.textContent = 'Authorizing protected Export download…';
    try {
      const response = await fetch(`${config.restUrl}${governanceEventPath()}/exports/${encodeURIComponent(String(record.id))}/download`, { credentials: 'same-origin', headers: requestHeaders() });
      if (!response.ok) throw new Error('download_failed');
      const blob = await response.blob();
      const disposition = response.headers.get('Content-Disposition') || '';
      const matched = disposition.match(/filename="([^"\\/]+)"/);
      const filename = matched ? matched[1] : `eventflow-export-${record.id}.${record.format}`;
      const objectUrl = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = objectUrl;
      anchor.download = filename;
      anchor.click();
      URL.revokeObjectURL(objectUrl);
      governanceNotice.textContent = 'Protected Export download started.';
    } catch (error) {
      governanceNotice.textContent = 'Export download failed. Request a new authorization by trying again.';
    }
  };

  const renderExports = (exports) => {
    exportList.replaceChildren();
    if (!exports.length) appendText(exportList, 'p', 'eventflow-admin__status', 'No Exports found.');
    exports.forEach((record) => {
      const { card, actions } = recordCard(
        `${record.type} export`, String(record.status || 'unknown'),
        [String(record.format || ''), record.contains_pii ? 'Contains PII' : 'No PII', record.expires_at ? `Expires ${record.expires_at}` : '', record.artifact_size_bytes ? `${record.artifact_size_bytes} bytes` : ''],
      );
      if (record.status === 'ready') actions.appendChild(actionButton('Download', () => downloadExport(record)));
      exportList.appendChild(card);
    });
  };

  const renderPrivacy = (actions, holds) => {
    privacyActionList.replaceChildren();
    holdList.replaceChildren();
    if (!actions.length) appendText(privacyActionList, 'p', 'eventflow-admin__status', 'No Privacy Actions found.');
    actions.forEach((record) => {
      const { card } = recordCard(`Invitation ${record.invitation_id}`, String(record.status || 'unknown'), [String(record.request_kind || ''), String(record.checkpoint || ''), String(record.policy_version || '')]);
      privacyActionList.appendChild(card);
    });
    if (!holds.length) appendText(holdList, 'p', 'eventflow-admin__status', 'No retention holds found.');
    holds.forEach((record) => {
      const { card, actions: controls } = recordCard(record.invitation_id ? `Invitation ${record.invitation_id}` : 'Event-wide hold', String(record.status || 'unknown'), [String(record.policy_version || ''), String(record.reason || '')]);
      if (record.status === 'active') controls.appendChild(actionButton('Release hold', async () => {
        if (!window.confirm('Release this retention hold?')) return;
        const result = await governanceMutation(`${governanceEventPath()}/retention-holds/${encodeURIComponent(String(record.id))}/release`, {});
        if (result) await loadGovernanceData();
      }, true));
      holdList.appendChild(card);
    });
  };

  const showAuditDetail = async (record) => {
    clearGovernanceDetails();
    governanceNotice.textContent = 'Loading protected Audit detail…';
    try {
      const { payload } = await requestJson(`${governanceEventPath()}/audit/${encodeURIComponent(String(record.id))}`);
      auditDetailContent.textContent = JSON.stringify(payload.data || {}, null, 2);
      auditDetail.hidden = false;
      governanceNotice.textContent = 'Audit detail loaded.';
    } catch (error) { governanceNotice.textContent = 'Audit detail unavailable.'; }
  };

  const renderAudit = (entries) => {
    auditList.replaceChildren();
    if (!entries.length) appendText(auditList, 'p', 'eventflow-admin__status', 'No Audit records match this filter.');
    entries.forEach((entry) => {
      const { card, actions } = recordCard(String(entry.summary || entry.action), String(entry.action || 'unknown'), [String(entry.entity_type || ''), entry.entity_id ? `Entity ${entry.entity_id}` : '', String(entry.source || ''), String(entry.occurred_at || '')]);
      actions.appendChild(actionButton('View detail', () => showAuditDetail(entry)));
      auditList.appendChild(card);
    });
  };

  const loadAudit = async () => {
    const query = ['limit=100'];
    const action = nullableText(auditFilterForm, 'action');
    const entity = nullableText(auditFilterForm, 'entity_type');
    if (action) query.push(`action=${encodeURIComponent(action)}`);
    if (entity) query.push(`entity_type=${encodeURIComponent(entity)}`);
    const { payload } = await requestJson(`${governanceEventPath()}/audit?${query.join('&')}`);
    renderAudit(payload.data || []);
  };

  const loadGovernanceData = async () => {
    if (!activeEvent) return;
    clearGovernanceDetails();
    governanceNotice.textContent = 'Loading privileged data domains…';
    const [imports, exports, privacyActions, holds, audit] = await Promise.allSettled([
      requestJson(`${governanceEventPath()}/imports?limit=100`),
      requestJson(`${governanceEventPath()}/exports?limit=100`),
      requestJson(`${governanceEventPath()}/privacy-actions?limit=100`),
      requestJson(`${governanceEventPath()}/retention-holds?limit=100`),
      requestJson(`${governanceEventPath()}/audit?limit=100`),
    ]);
    const failures = [];
    if (imports.status === 'fulfilled') renderImports(imports.value.payload.data || []); else { importList.replaceChildren(); failures.push('Imports unavailable.'); }
    if (exports.status === 'fulfilled') renderExports(exports.value.payload.data || []); else { exportList.replaceChildren(); failures.push('Exports unavailable.'); }
    if (privacyActions.status === 'fulfilled' && holds.status === 'fulfilled') renderPrivacy(privacyActions.value.payload.data || [], holds.value.payload.data || []);
    else { privacyActionList.replaceChildren(); holdList.replaceChildren(); failures.push('Privacy administration unavailable.'); }
    if (audit.status === 'fulfilled') renderAudit(audit.value.payload.data || []); else { auditList.replaceChildren(); failures.push('Audit unavailable.'); }
    disableForm(importForm, imports.status !== 'fulfilled');
    disableForm(exportForm, exports.status !== 'fulfilled');
    disableForm(privacyActionForm, privacyActions.status !== 'fulfilled');
    disableForm(holdForm, holds.status !== 'fulfilled');
    disableForm(auditFilterForm, audit.status !== 'fulfilled');
    governanceNotice.textContent = failures.join(' ') || 'Privileged data domains loaded.';
  };

  const openGovernance = async () => {
    if (!activeEvent) return;
    clearCredential();
    clearCommunicationDetails();
    setup.hidden = true;
    people.hidden = true;
    seating.hidden = true;
    reception.hidden = true;
    communications.hidden = true;
    overviewFacts.hidden = true;
    governance.hidden = false;
    selectGovernanceTab('imports');
    await loadGovernanceData();
  };

  const submitImport = async (submissionEvent) => {
    submissionEvent.preventDefault();
    governanceNotice.textContent = 'Uploading and staging Import securely…';
    const body = new FormData(importForm);
    try {
      const response = await fetch(`${config.restUrl}${governanceEventPath()}/imports`, { method: 'POST', credentials: 'same-origin', headers: requestHeaders({ 'Idempotency-Key': idempotencyKey() }), body });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.code || 'upload_failed');
      importForm.reset();
      await loadGovernanceData();
      governanceNotice.textContent = 'Import staged. Configure and validate its column mapping before apply.';
    } catch (error) { governanceNotice.textContent = 'Import upload failed. Verify the file and try again.'; }
  };

  const submitExport = async (submissionEvent) => {
    submissionEvent.preventDefault();
    const containsPii = field(exportForm, 'type').value !== 'event_summary';
    if (containsPii && !window.confirm('Request this PII-containing Export for the stated purpose?')) return;
    const result = await governanceMutation(`${governanceEventPath()}/exports`, { type: String(field(exportForm, 'type').value), format: String(field(exportForm, 'format').value), purpose: String(field(exportForm, 'purpose').value).trim() });
    if (result) { exportForm.reset(); await loadGovernanceData(); }
  };

  const submitPrivacyAction = async (submissionEvent) => {
    submissionEvent.preventDefault();
    if (!window.confirm('Request this destructive Privacy Action? Processing may be irreversible.')) return;
    const result = await governanceMutation(`${governanceEventPath()}/privacy-actions`, { invitation_id: Number(field(privacyActionForm, 'invitation_id').value), policy_version: String(field(privacyActionForm, 'policy_version').value).trim(), purpose: String(field(privacyActionForm, 'purpose').value).trim() });
    if (result) { privacyActionForm.reset(); await loadGovernanceData(); }
  };

  const submitHold = async (submissionEvent) => {
    submissionEvent.preventDefault();
    const invitation = String(field(holdForm, 'invitation_id').value || '');
    const result = await governanceMutation(`${governanceEventPath()}/retention-holds`, { invitation_id: invitation ? Number(invitation) : null, policy_version: String(field(holdForm, 'policy_version').value).trim(), reason: String(field(holdForm, 'reason').value).trim() });
    if (result) { holdForm.reset(); await loadGovernanceData(); }
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
      clearCredential();
      activeEvent = null;
      activeEventEtag = null;
      activeConfigurationEtag = null;
      overview.hidden = true;
      seating.hidden = true;
      reception.hidden = true;
      communications.hidden = true;
      governance.hidden = true;
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
  peopleClose.addEventListener('click', () => {
    clearCredential();
    resetInvitationEditor();
    people.hidden = true;
    overviewFacts.hidden = false;
    overviewMessage.textContent = 'People workspace closed.';
  });
  seatingClose.addEventListener('click', () => {
    seating.hidden = true;
    overviewFacts.hidden = false;
    overviewMessage.textContent = 'Seating workspace closed.';
  });
  receptionClose.addEventListener('click', () => {
    reception.hidden = true;
    receptionAttendees = [];
    overviewFacts.hidden = false;
    overviewMessage.textContent = 'Reception workspace closed.';
  });
  communicationsClose.addEventListener('click', () => {
    clearCommunicationDetails();
    communications.hidden = true;
    overviewFacts.hidden = false;
    overviewMessage.textContent = 'Communications workspace closed.';
  });
  governanceClose.addEventListener('click', () => {
    clearGovernanceDetails();
    governance.hidden = true;
    overviewFacts.hidden = false;
    overviewMessage.textContent = 'Data and governance workspace closed.';
  });
  root.addEventListener('invalid', reportInvalidControl, true);
  root.addEventListener('input', clearInvalidControl);
  configureTabs(peopleTabs, selectPeopleTab);
  membershipForm.addEventListener('submit', submitMembership);
  invitationForm.addEventListener('submit', submitInvitation);
  invitationFilter.addEventListener('input', () => renderInvitations());
  invitationStateFilter.addEventListener('change', () => renderInvitations());
  invitationEditCancel.addEventListener('click', () => {
    resetInvitationEditor();
    peopleNotice.textContent = 'Invitation edit cancelled.';
  });
  attendeeForm.addEventListener('submit', submitAttendee);
  tableForm.addEventListener('submit', submitTable);
  groupForm.addEventListener('submit', submitGroup);
  placementForm.addEventListener('submit', submitPlacement);
  placementTable.addEventListener('change', populatePlacementSeats);
  recommendationForm.addEventListener('submit', submitRecommendation);
  receptionSearchForm.addEventListener('submit', (submissionEvent) => {
    submissionEvent.preventDefault();
    refreshReceptionSearch();
  });
  receptionBulkCheckIn.addEventListener('click', () => {
    const attendeeIds = Array.from(receptionResults.querySelectorAll('input[name="reception_attendee"]:checked'))
      .map((checkbox) => Number(checkbox.value));
    checkInAttendees(attendeeIds);
  });
  configureTabs(communicationTabs, selectCommunicationTab);
  invitationComposer.addEventListener('submit', (submissionEvent) => submissionEvent.preventDefault());
  invitationChannel.addEventListener('change', configureInvitationChannel);
  [invitationSubject, invitationImage, invitationMessage].forEach((control) => control.addEventListener('input', invalidatePreparedInvitation));
  invitationRecipientSearch.addEventListener('input', renderInvitationRecipients);
  invitationPhoneRegion.addEventListener('change', renderInvitationRecipients);
  invitationSelectVisible.addEventListener('click', () => {
    visibleInvitationRecipients().forEach((invitation) => selectedInvitationRecipients.add(Number(invitation.id)));
    invalidatePreparedInvitation();
    renderInvitationRecipients();
  });
  invitationClearSelection.addEventListener('click', () => {
    selectedInvitationRecipients.clear();
    invalidatePreparedInvitation();
    renderInvitationRecipients();
  });
  invitationImageChoose.addEventListener('click', () => {
    if (!window.wp?.media) {
      communicationsNotice.textContent = 'The Media Library picker is unavailable. Paste the public image URL into the field instead.';
      invitationImage.focus();
      return;
    }
    const mediaFrame = window.wp.media({ title: 'Choose invitation card', button: { text: 'Use this invitation card' }, library: { type: 'image' }, multiple: false });
    mediaFrame.on('select', () => {
      const attachment = mediaFrame.state().get('selection').first()?.toJSON();
      if (!attachment?.url) return;
      invitationImage.value = String(attachment.url);
      invalidatePreparedInvitation();
      communicationsNotice.textContent = 'Invitation-card image selected.';
    });
    mediaFrame.open();
  });
  invitationTestSend.addEventListener('click', sendInvitationTest);
  invitationReview.addEventListener('click', reviewInvitationCampaign);
  invitationSend.addEventListener('click', sendPreparedInvitations);
  templateForm.addEventListener('submit', submitTemplate);
  campaignForm.addEventListener('submit', submitCampaign);
  messageFilterForm.addEventListener('submit', async (submissionEvent) => {
    submissionEvent.preventDefault();
    clearCommunicationDetails();
    communicationsNotice.textContent = 'Filtering Messages…';
    try {
      await loadMessages();
      communicationsNotice.textContent = 'Message filter applied.';
    } catch (error) {
      messageList.replaceChildren();
      communicationsNotice.textContent = 'Messages could not be filtered. Check the filter and try again.';
    }
  });
  templatePreviewClear.addEventListener('click', clearCommunicationDetails);
  messageDetailClear.addEventListener('click', clearCommunicationDetails);
  configureTabs(governanceTabs, selectGovernanceTab);
  importForm.addEventListener('submit', submitImport);
  exportForm.addEventListener('submit', submitExport);
  privacyActionForm.addEventListener('submit', submitPrivacyAction);
  holdForm.addEventListener('submit', submitHold);
  auditFilterForm.addEventListener('submit', async (submissionEvent) => {
    submissionEvent.preventDefault();
    clearGovernanceDetails();
    governanceNotice.textContent = 'Filtering Audit history…';
    try { await loadAudit(); governanceNotice.textContent = 'Audit filter applied.'; }
    catch (error) { auditList.replaceChildren(); governanceNotice.textContent = 'Audit history could not be filtered.'; }
  });
  auditIntegrity.addEventListener('click', async () => {
    clearGovernanceDetails();
    governanceNotice.textContent = 'Verifying the pinned Audit chain…';
    try {
      const { payload } = await requestJson(`${governanceEventPath()}/audit/integrity`);
      const report = payload.data || {};
      appendText(auditIntegrityResult, 'p', report.valid ? 'eventflow-integrity--valid' : 'eventflow-integrity--invalid', report.valid
        ? `Audit chain verified across ${report.record_count} records.`
        : `Audit integrity failure: ${report.failure_code || 'unknown'}.`);
      governanceNotice.textContent = report.valid ? 'Audit integrity verified.' : 'Audit integrity verification failed.';
    } catch (error) { governanceNotice.textContent = 'Audit integrity could not be verified.'; }
  });
  diagnosticsLoad.addEventListener('click', async () => {
    clearGovernanceDetails();
    governanceNotice.textContent = 'Loading sanitized diagnostics…';
    try {
      const { payload } = await requestJson(`${governanceEventPath()}/diagnostics`);
      diagnosticsContent.textContent = JSON.stringify(payload.data || {}, null, 2);
      diagnosticsDetail.hidden = false;
      governanceNotice.textContent = 'Sanitized diagnostics loaded. Raw logs are not available in this workspace.';
    } catch (error) { governanceNotice.textContent = 'Diagnostics unavailable for this Event or current role.'; }
  });
  importDetailClear.addEventListener('click', clearGovernanceDetails);
  auditDetailClear.addEventListener('click', clearGovernanceDetails);
  diagnosticsClear.addEventListener('click', clearGovernanceDetails);
  credentialClear.addEventListener('click', clearCredential);
  credentialCopy.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(credentialToken.value);
      peopleNotice.textContent = 'Credential copied. Clear it after secure delivery.';
    } catch (error) {
      credentialToken.focus();
      credentialToken.select();
      peopleNotice.textContent = 'Automatic copy was unavailable. The credential is selected for manual copying.';
    }
  });
  loadEvents();
})();
