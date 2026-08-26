(() => {
  'use strict';

  const root = document.getElementById('eventflow-guest');
  if (!root) return;

  const config = window.EventFlowGuest || {};
  const region = document.getElementById('eventflow-guest-region');
  const title = document.getElementById('eventflow-guest-title');
  const status = document.getElementById('eventflow-guest-status');
  const contextRegion = document.getElementById('eventflow-guest-context');
  const salutation = document.getElementById('eventflow-guest-salutation');
  const welcome = document.getElementById('eventflow-guest-welcome');
  const facts = document.getElementById('eventflow-guest-facts');
  const notice = document.getElementById('eventflow-guest-notice');
  const form = document.getElementById('eventflow-rsvp-form');
  const attendeeRegion = document.getElementById('eventflow-guest-attendees');
  const attendeeList = document.getElementById('eventflow-guest-attendee-list');
  const addGuest = document.getElementById('eventflow-add-guest');
  const partyCapacity = document.getElementById('eventflow-party-capacity');
  const confirmation = document.getElementById('eventflow-guest-confirmation');
  const logout = document.getElementById('eventflow-guest-logout');
  let csrfToken = null;
  let responseEtag = null;
  let invitationContext = null;
  let rsvpEditable = false;

  const setStatus = (message, busy = false) => {
    status.textContent = message;
    region.setAttribute('aria-busy', busy ? 'true' : 'false');
  };

  const reportInvalidControl = (validationEvent) => {
    const control = validationEvent.target;
    const summaryId = 'eventflow-rsvp-error-summary';
    let summary = document.getElementById(summaryId);
    if (!summary) {
      summary = document.createElement('p');
      summary.className = 'eventflow-form-error';
      summary.id = summaryId;
      summary.setAttribute('role', 'alert');
      summary.textContent = 'Check the highlighted fields and correct the information before saving your RSVP.';
      form.prepend(summary);
    }
    control.setAttribute('aria-invalid', 'true');
    control.setAttribute('aria-describedby', summaryId);
  };

  const clearInvalidControl = (inputEvent) => {
    const control = inputEvent.target;
    if (!control.matches('input') || !control.validity?.valid) return;
    control.removeAttribute('aria-invalid');
    control.removeAttribute('aria-describedby');
    if (form.querySelector('[aria-invalid="true"]')) return;
    const summary = document.getElementById('eventflow-rsvp-error-summary');
    if (summary) summary.remove();
  };

  const cleanCredentialFragment = () => {
    const fragment = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : '';
    const parameters = new URLSearchParams(fragment);
    const credential = parameters.get('eventflow-invitation');
    window.history.replaceState(null, document.title, `${window.location.pathname}${window.location.search}`);
    return typeof credential === 'string' && /^[a-f0-9]{64}$/.test(credential) ? credential : null;
  };

  const isInvitationPreviewFragment = () => {
    const fragment = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : '';
    return new URLSearchParams(fragment).get('eventflow-preview') === '1';
  };

  const renderInvitationPreview = () => {
    title.textContent = 'Invitation link test';
    salutation.textContent = 'Your test link is working.';
    welcome.textContent = 'Live guest emails will contain a unique secure link that opens their RSVP and companion form.';
    facts.replaceChildren();
    addFact('RSVP deadline', 'September 2, 2026');
    notice.textContent = 'This safe preview does not save an RSVP or change any guest record.';
    form.hidden = true;
    logout.hidden = true;
    contextRegion.hidden = false;
    setStatus('Test invitation link verified.');
  };

  const request = async (path, options = {}) => {
    const response = await fetch(`${config.restUrl}${path}`, {
      cache: 'no-store',
      credentials: 'same-origin',
      ...options,
    });
    let payload = {};
    if (response.status !== 204) {
      try { payload = await response.json(); } catch (error) { payload = {}; }
    }
    if (!response.ok) {
      const failure = new Error(payload.message || payload.code || 'request_failed');
      failure.code = payload.code || 'request_failed';
      failure.requestId = payload.request_id || response.headers.get('X-Request-ID') || '';
      failure.status = response.status;
      throw failure;
    }
    return { payload, etag: response.headers.get('ETag') };
  };

  const idempotencyKey = () => {
    if (!window.crypto || !window.crypto.getRandomValues) throw new Error('secure_random_unavailable');
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return `eventflow-guest-${Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('')}`;
  };

  const formatDate = (value) => {
    if (!value) return 'Not scheduled';
    try {
      return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'full', timeStyle: 'short', timeZone: invitationContext?.timezone || 'UTC',
      }).format(new Date(value));
    } catch (error) {
      return String(value);
    }
  };

  const addFact = (label, value) => {
    const term = document.createElement('dt');
    const description = document.createElement('dd');
    term.textContent = label;
    description.textContent = value;
    facts.append(term, description);
  };

  const input = (labelText, type, value = '') => {
    const label = document.createElement('label');
    const caption = document.createElement('span');
    const control = document.createElement('input');
    caption.textContent = labelText;
    control.type = type;
    control.value = value || '';
    label.append(caption, control);
    return { label, control };
  };

  const attendeeRow = (attendee, role) => {
    const row = document.createElement('fieldset');
    row.className = 'eventflow-guest__attendee';
    row.dataset.attendeeId = attendee?.id ? String(attendee.id) : '';
    row.dataset.role = role;
    const legend = document.createElement('legend');
    const companionNumber = role === 'primary' ? 0 : attendeeList.querySelectorAll('[data-role="companion"]').length + 1;
    legend.textContent = role === 'primary' ? 'You' : `Companion ${companionNumber}`;
    row.appendChild(legend);
    const name = input('Name', 'text', attendee?.display_name || '');
    name.control.required = true;
    name.control.maxLength = 190;
    name.control.dataset.field = 'display_name';
    const email = input('Email', 'email', attendee?.email || '');
    email.control.dataset.field = 'email';
    const phone = input('Phone', 'tel', attendee?.phone || '');
    phone.control.dataset.field = 'phone';
    const dietary = input('Dietary requirements', 'text', attendee?.dietary_requirements || '');
    dietary.control.dataset.field = 'dietary_requirements';
    const accessibility = input('Accessibility requirements', 'text', attendee?.accessibility_requirements || '');
    accessibility.control.dataset.field = 'accessibility_requirements';
    row.append(name.label, email.label, phone.label, dietary.label, accessibility.label);
    if (role !== 'primary') {
      const remove = document.createElement('button');
      remove.className = 'eventflow-guest__link';
      remove.type = 'button';
      remove.textContent = 'Remove companion';
      remove.addEventListener('click', () => {
        row.remove();
        updateAddGuestState();
      });
      row.appendChild(remove);
    }
    attendeeList.appendChild(row);
  };

  const selectedResponse = () => form.querySelector('input[name="response_status"]:checked')?.value || null;

  const updateAddGuestState = () => {
    const accepting = selectedResponse() === 'accepted';
    const capacity = Number(invitationContext?.capacity || 1);
    const used = attendeeList.children.length;
    const remaining = Math.max(0, capacity - used);
    attendeeList.querySelectorAll('[data-role="companion"] legend').forEach((legend, index) => {
      legend.textContent = `Companion ${index + 1}`;
    });
    attendeeRegion.hidden = !accepting;
    partyCapacity.textContent = capacity > 1
      ? `You may add up to ${capacity - 1} companion${capacity - 1 === 1 ? '' : 's'}. ${used} of ${capacity} places named. ${remaining ? `${remaining} remaining.` : 'Your party is complete.'}`
      : 'This invitation is reserved for you.';
    addGuest.hidden = capacity <= 1;
    addGuest.disabled = !rsvpEditable || !accepting || used >= capacity;
  };

  const formAllowed = (context) => {
    if (!csrfToken) return false;
    if (!context.allow_guest_edits && context.response_status !== 'pending') return false;
    const now = Date.now();
    if (context.confirmation_opens_at && now < Date.parse(context.confirmation_opens_at)) return false;
    if (context.confirmation_closes_at && now > Date.parse(context.confirmation_closes_at)) return false;
    return true;
  };

  const render = (context, response) => {
    invitationContext = context;
    title.textContent = context.event_name || 'Your invitation';
    salutation.textContent = context.primary_name ? `Dear ${context.primary_name},` : 'Welcome,';
    welcome.textContent = context.welcome_message || 'You are warmly invited to celebrate with us. Please confirm your attendance and add the names of any companions joining you.';
    facts.replaceChildren();
    addFact('Starts', formatDate(context.starts_at));
    addFact('Ends', formatDate(context.ends_at));
    if (context.dress_code) addFact('Dress code', String(context.dress_code));
    notice.textContent = context.surprise_notice || '';
    attendeeList.replaceChildren();
    const attendees = Array.isArray(response.attendees) ? response.attendees : [];
    if (attendees.length) {
      attendees.forEach((attendee) => attendeeRow(attendee, attendee.role === 'primary' ? 'primary' : 'companion'));
    } else {
      attendeeRow({ display_name: context.primary_name }, 'primary');
    }
    const responseStatus = response.response_status || context.response_status || 'pending';
    const selected = form.querySelector(`input[name="response_status"][value="${responseStatus}"]`);
    if (selected) selected.checked = true;
    contextRegion.hidden = false;
    confirmation.textContent = responseStatus === 'accepted'
      ? (context.confirmation_message || 'Your RSVP is confirmed.')
      : (responseStatus === 'declined' ? 'Your response has been recorded.' : '');
    const allowed = formAllowed(context);
    rsvpEditable = allowed;
    Array.from(form.elements).forEach((control) => { control.disabled = !allowed; });
    if (!allowed) {
      if (!csrfToken) notice.textContent = 'Reopen your original secure invitation link to make changes.';
      else if (!context.allow_guest_edits && responseStatus !== 'pending') notice.textContent = 'This response can no longer be edited online.';
      else notice.textContent = 'The RSVP window is currently closed.';
    }
    logout.hidden = !csrfToken;
    updateAddGuestState();
    setStatus('Secure invitation loaded.');
  };

  const loadInvitation = async () => {
    const contextResult = await request('public/invitation');
    const responseResult = await request('public/invitation/response');
    responseEtag = responseResult.etag;
    render(contextResult.payload.data || {}, responseResult.payload.data || {});
  };

  const bootstrap = async (credential, credentialType) => {
    const result = await request('public/invitations/bootstrap', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ credential, credential_type: credentialType }),
    });
    csrfToken = result.payload.data?.csrf_token || null;
  };

  const attendeePayload = () => Array.from(attendeeList.children).map((row) => {
    const value = (name) => {
      const candidate = String(row.querySelector(`[data-field="${name}"]`).value || '').trim();
      return candidate === '' ? null : candidate;
    };
    const attendeeId = String(row.dataset.attendeeId || '');
    return {
      attendee_id: attendeeId === '' ? null : Number(attendeeId),
      display_name: value('display_name') || '',
      role: row.dataset.role,
      email: value('email'),
      phone: value('phone'),
      dietary_requirements: value('dietary_requirements'),
      accessibility_requirements: value('accessibility_requirements'),
    };
  });

  const submitRsvp = async (submissionEvent) => {
    submissionEvent.preventDefault();
    if (!csrfToken || !responseEtag) return;
    const responseStatus = selectedResponse();
    if (!responseStatus) return;
    Array.from(form.elements).forEach((control) => { control.disabled = true; });
    confirmation.textContent = 'Saving your response…';
    try {
      const result = await request('public/invitation/response', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Idempotency-Key': idempotencyKey(),
          'If-Match': responseEtag,
          'X-EventFlow-CSRF': csrfToken,
        },
        body: JSON.stringify({
          response_status: responseStatus,
          attendees: responseStatus === 'accepted' ? attendeePayload() : [],
        }),
      });
      responseEtag = result.etag;
      render(invitationContext, result.payload.data || {});
      confirmation.textContent = responseStatus === 'accepted'
        ? (invitationContext.confirmation_message || 'Thank you. Your RSVP is confirmed.')
        : 'Thank you. Your response has been recorded.';
    } catch (error) {
      const reference = error.requestId ? ` Request ID: ${error.requestId}.` : '';
      confirmation.textContent = `Your response could not be confirmed. Reload the secure invitation before retrying.${reference}`;
      Array.from(form.elements).forEach((control) => { control.disabled = false; });
      updateAddGuestState();
    }
  };

  const initialize = async () => {
    const invitationPreview = isInvitationPreviewFragment();
    const invitationCredential = cleanCredentialFragment();
    let openingPhase = 'bootstrap';
    if (!config.ready) {
      setStatus('EventFlow is temporarily unavailable. Please try again later.');
      return;
    }
    if (invitationPreview) {
      renderInvitationPreview();
      return;
    }
    try {
      if (invitationCredential) {
        try {
          await bootstrap(invitationCredential, 'message_link');
        } catch (error) {
          if (error.code !== 'guest_session_invalid') throw error;
          await bootstrap(invitationCredential, 'invitation');
        }
      }
      openingPhase = 'invitation';
      await loadInvitation();
    } catch (error) {
      const reference = error.requestId ? ` Reference: ${error.requestId}.` : '';
      if (openingPhase === 'invitation' && error.code === 'guest_session_invalid') {
        setStatus(`This secure browser session has expired. Reopen the original invitation email and click its personalized link—not a saved or refreshed Confirm page.${reference}`);
        return;
      }
      setStatus(openingPhase === 'bootstrap'
        ? `This secure invitation could not be opened. Use the complete invitation link or request a new one.${reference}`
        : `Your secure link was accepted, but the invitation details could not be loaded. Please try again.${reference}`);
    }
  };

  form.addEventListener('change', (event) => {
    if (event.target?.name === 'response_status') updateAddGuestState();
  });
  form.addEventListener('submit', submitRsvp);
  form.addEventListener('invalid', reportInvalidControl, true);
  form.addEventListener('input', clearInvalidControl);
  addGuest.addEventListener('click', () => {
    if (attendeeList.children.length >= Number(invitationContext?.capacity || 1)) return;
    attendeeRow(null, 'companion');
    updateAddGuestState();
  });
  logout.addEventListener('click', async () => {
    if (!csrfToken) return;
    try {
      await request('public/session/logout', {
        method: 'POST',
        headers: { 'X-EventFlow-CSRF': csrfToken },
      });
      csrfToken = null;
      responseEtag = null;
      rsvpEditable = false;
      contextRegion.hidden = true;
      setStatus('This secure session is closed. You may close this page.');
    } catch (error) {
      setStatus('The session could not be closed. It will expire automatically.');
    }
  });

  initialize();
})();
