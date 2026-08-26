<?php

namespace EventFlow\Presentation\Admin;

use EventFlow\Bootstrap\BootstrapResult;

final readonly class AdminShellView
{
    public function render(BootstrapResult $bootstrap): string
    {
        $state = $this->escape($bootstrap->state->value);
        $ready = $bootstrap->ready ? 'true' : 'false';

        return <<<HTML
<div class="wrap eventflow-admin" id="eventflow-admin" data-bootstrap-state="{$state}" data-ready="{$ready}">
  <header class="eventflow-admin__header">
    <div>
      <p class="eventflow-admin__eyebrow">Event operations</p>
      <h1>EventFlow</h1>
      <p class="eventflow-admin__lede">Plan, invite, seat, communicate, and welcome guests from one workspace.</p>
    </div>
    <button class="button button-secondary" id="eventflow-refresh" type="button">Refresh events</button>
  </header>
  <div class="notice notice-warning inline eventflow-admin__bootstrap" id="eventflow-bootstrap-notice" hidden>
    <p>EventFlow is not ready. An administrator must resolve the reported system status before event data can load.</p>
  </div>
  <main aria-busy="true" aria-live="polite" id="eventflow-event-region">
    <p class="eventflow-admin__status" id="eventflow-status">Loading accessible events…</p>
    <section id="eventflow-events-view" aria-labelledby="eventflow-events-heading">
      <h2 class="screen-reader-text" id="eventflow-events-heading">Accessible events</h2>
      <div class="eventflow-admin__grid" id="eventflow-event-list"></div>
    </section>
    <section class="eventflow-overview" id="eventflow-overview" aria-labelledby="eventflow-overview-title" hidden>
      <button class="button-link eventflow-overview__back" id="eventflow-overview-back" type="button">&larr; All events</button>
      <div class="eventflow-overview__heading">
        <div>
          <p class="eventflow-event-card__status" id="eventflow-overview-status"></p>
          <h2 id="eventflow-overview-title">Event overview</h2>
        </div>
        <div class="eventflow-overview__actions" id="eventflow-overview-actions" aria-label="Event lifecycle actions"></div>
      </div>
      <dl class="eventflow-overview__facts" id="eventflow-overview-facts"></dl>
      <p class="eventflow-overview__message" id="eventflow-overview-message" role="status"></p>
      <section class="eventflow-dashboard" id="eventflow-dashboard" aria-labelledby="eventflow-dashboard-title">
        <div class="eventflow-dashboard__heading">
          <div>
            <p class="eventflow-admin__eyebrow">Guest responses</p>
            <h3 id="eventflow-dashboard-title">Event dashboard</h3>
            <p>See who has replied, who still owes companion names, and prepare targeted reminders without searching through every contact.</p>
          </div>
          <div class="eventflow-dashboard__primary-actions">
            <button class="button button-secondary" id="eventflow-dashboard-email-reminder" type="button" disabled>Prepare email reminder</button>
            <button class="button button-secondary" id="eventflow-dashboard-sms-reminder" type="button" disabled>Prepare SMS reminder</button>
          </div>
        </div>
        <div class="eventflow-dashboard__metrics" id="eventflow-dashboard-metrics" aria-live="polite"></div>
        <div class="eventflow-dashboard__filters">
          <div><label for="eventflow-dashboard-search">Find a guest</label><input id="eventflow-dashboard-search" placeholder="Name, email, phone, or guest code" type="search"></div>
          <div><label for="eventflow-dashboard-status-filter">Response status</label><select id="eventflow-dashboard-status-filter"><option value="all">All guests</option><option value="action_required">Needs a reminder</option><option value="pending">Awaiting RSVP</option><option value="incomplete">Missing companion names</option><option value="accepted">Confirmed</option><option value="declined">Declined</option></select></div>
          <div class="eventflow-dashboard__selection-actions"><button class="button button-secondary" id="eventflow-dashboard-select-action" type="button">Select guests needing action</button><button class="button-link" id="eventflow-dashboard-clear-selection" type="button">Clear</button></div>
        </div>
        <p class="eventflow-dashboard__selection" id="eventflow-dashboard-selection" role="status">Loading guest progress…</p>
        <div class="eventflow-dashboard__table-wrap">
          <table class="widefat striped eventflow-dashboard__table">
            <thead><tr><th class="check-column" scope="col"><span class="screen-reader-text">Select</span></th><th scope="col">Primary guest</th><th scope="col">Contact</th><th scope="col">RSVP</th><th scope="col">Seats</th><th scope="col">Companions</th><th scope="col">Next action</th></tr></thead>
            <tbody id="eventflow-dashboard-guest-body"></tbody>
          </table>
        </div>
        <p class="eventflow-admin__status" id="eventflow-dashboard-empty" hidden>No guests match this view.</p>
      </section>
      <section class="eventflow-setup" id="eventflow-setup" aria-labelledby="eventflow-setup-title" hidden>
        <div class="eventflow-setup__heading">
          <div>
            <p class="eventflow-admin__eyebrow">Configuration</p>
            <h3 id="eventflow-setup-title">Event setup</h3>
          </div>
          <button class="button-link" id="eventflow-setup-close" type="button">Close setup</button>
        </div>
        <p class="eventflow-setup__notice" id="eventflow-setup-notice" role="status"></p>
        <div class="eventflow-setup__columns">
          <form class="eventflow-form" id="eventflow-event-form">
            <fieldset>
              <legend>Event details</legend>
              <p class="description">Event details can be changed only while the Event is a draft.</p>
              <label for="eventflow-event-name">Name</label>
              <input class="regular-text" id="eventflow-event-name" name="name" maxlength="190" required type="text">
              <label for="eventflow-event-slug">Slug</label>
              <input class="regular-text" id="eventflow-event-slug" name="slug" maxlength="190" pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?" required type="text">
              <label for="eventflow-event-timezone">Timezone</label>
              <input class="regular-text" id="eventflow-event-timezone" name="timezone" required type="text">
              <label for="eventflow-event-starts">Starts at</label>
              <input class="regular-text" id="eventflow-event-starts" name="starts_at" placeholder="2026-09-01T18:00:00-06:00" type="text">
              <label for="eventflow-event-ends">Ends at</label>
              <input class="regular-text" id="eventflow-event-ends" name="ends_at" placeholder="2026-09-01T22:00:00-06:00" type="text">
              <label for="eventflow-event-venue">Venue</label>
              <select id="eventflow-event-venue" name="venue_id"><option value="">No venue selected</option></select>
              <button class="button button-primary" type="submit">Save Event details</button>
            </fieldset>
          </form>
          <form class="eventflow-form" id="eventflow-configuration-form">
            <fieldset>
              <legend>Guest and seating settings</legend>
              <label for="eventflow-welcome-message">Welcome message</label>
              <textarea class="large-text" id="eventflow-welcome-message" name="welcome_message" rows="4"></textarea>
              <label for="eventflow-confirmation-message">Confirmation message</label>
              <textarea class="large-text" id="eventflow-confirmation-message" name="confirmation_message" rows="4"></textarea>
              <label for="eventflow-dress-code">Dress code</label>
              <input class="regular-text" id="eventflow-dress-code" name="dress_code" maxlength="255" type="text">
              <label for="eventflow-confirmation-opens">RSVP opens</label>
              <input class="regular-text" id="eventflow-confirmation-opens" name="confirmation_opens_at" placeholder="2026-07-01T09:00:00-06:00" type="text">
              <label for="eventflow-confirmation-closes">RSVP closes</label>
              <input class="regular-text" id="eventflow-confirmation-closes" name="confirmation_closes_at" placeholder="2026-08-15T23:59:00-06:00" type="text">
              <label for="eventflow-seating-mode">Seating mode</label>
              <select id="eventflow-seating-mode" name="seating_mode"><option value="table">Table</option><option value="seat">Assigned seat</option></select>
              <label class="eventflow-form__check"><input id="eventflow-guest-edits" name="allow_guest_edits" type="checkbox"> Allow guests to edit responses</label>
              <label class="eventflow-form__check"><input id="eventflow-automatic-seating" name="automatic_seating_enabled" type="checkbox"> Enable assisted seating</label>
              <button class="button button-primary" type="submit">Save guest settings</button>
            </fieldset>
          </form>
        </div>
        <details class="eventflow-venue-create">
          <summary>Add a venue</summary>
          <form class="eventflow-form eventflow-form--compact" id="eventflow-venue-form">
            <label for="eventflow-venue-name">Venue name</label>
            <input class="regular-text" id="eventflow-venue-name" name="name" maxlength="190" required type="text">
            <label for="eventflow-venue-city">City</label>
            <input class="regular-text" id="eventflow-venue-city" name="city" maxlength="120" type="text">
            <label for="eventflow-venue-country">Country code</label>
            <input id="eventflow-venue-country" name="country_code" maxlength="2" pattern="[A-Za-z]{2}" size="4" type="text">
            <label for="eventflow-venue-capacity">Default capacity</label>
            <input id="eventflow-venue-capacity" min="1" name="default_capacity" type="number">
            <button class="button button-secondary" type="submit">Create venue</button>
          </form>
        </details>
      </section>
      <section class="eventflow-people" id="eventflow-people" aria-labelledby="eventflow-people-title" hidden>
        <div class="eventflow-setup__heading">
          <div>
            <p class="eventflow-admin__eyebrow">People</p>
            <h3 id="eventflow-people-title">Memberships, invitations, and attendees</h3>
          </div>
          <button class="button-link" id="eventflow-people-close" type="button">Close people workspace</button>
        </div>
        <p class="eventflow-setup__notice" id="eventflow-people-notice" role="status"></p>
        <div class="eventflow-credential" id="eventflow-credential" hidden>
          <label for="eventflow-credential-token">Invitation credential — copy now; it cannot be shown again</label>
          <div class="eventflow-credential__row">
            <input autocomplete="off" id="eventflow-credential-token" readonly spellcheck="false" type="text">
            <button class="button button-secondary" id="eventflow-credential-copy" type="button">Copy credential</button>
            <button class="button-link-delete" id="eventflow-credential-clear" type="button">Clear credential</button>
          </div>
        </div>
        <div class="eventflow-people__tabs" role="tablist" aria-label="People administration">
          <button aria-controls="eventflow-memberships-panel" aria-selected="true" class="button" id="eventflow-memberships-tab" role="tab" type="button">Team</button>
          <button aria-controls="eventflow-invitations-panel" aria-selected="false" class="button" id="eventflow-invitations-tab" role="tab" type="button">Invitations</button>
          <button aria-controls="eventflow-attendees-panel" aria-selected="false" class="button" id="eventflow-attendees-tab" role="tab" type="button">Attendees</button>
        </div>
        <section id="eventflow-memberships-panel" role="tabpanel" aria-labelledby="eventflow-memberships-tab">
          <form class="eventflow-inline-form" id="eventflow-membership-form">
            <label for="eventflow-member-user">WordPress user ID</label>
            <input id="eventflow-member-user" min="1" name="user_id" required type="number">
            <label for="eventflow-member-role">Role</label>
            <select id="eventflow-member-role" name="role"><option value="organizer">Organizer</option><option value="coordinator">Coordinator</option><option value="reception">Reception</option><option value="reporting">Reporting</option><option value="owner">Owner</option></select>
            <label for="eventflow-member-expires">Expires at</label>
            <input id="eventflow-member-expires" name="expires_at" placeholder="Optional ISO 8601 timestamp" type="text">
            <button class="button button-secondary" type="submit">Grant membership</button>
          </form>
          <div class="eventflow-record-list" id="eventflow-membership-list"></div>
        </section>
        <section id="eventflow-invitations-panel" role="tabpanel" aria-labelledby="eventflow-invitations-tab" hidden>
          <details class="eventflow-guest-editor" id="eventflow-invitation-editor">
            <summary>Add or edit a guest</summary>
          <form class="eventflow-inline-form eventflow-inline-form--wide" id="eventflow-invitation-form">
            <label for="eventflow-invitation-name">Primary guest</label>
            <input id="eventflow-invitation-name" maxlength="190" name="primary_name" required type="text">
            <label for="eventflow-invitation-email">Email</label>
            <input id="eventflow-invitation-email" name="primary_email" type="email">
            <label for="eventflow-invitation-phone">Phone</label>
            <input id="eventflow-invitation-phone" name="primary_phone" type="tel">
            <label for="eventflow-invitation-capacity">Capacity</label>
            <input id="eventflow-invitation-capacity" max="65535" min="1" name="capacity" required type="number" value="1">
            <label for="eventflow-invitation-expiry">Credential expires</label>
            <input id="eventflow-invitation-expiry" name="token_expires_at" placeholder="Optional ISO 8601 timestamp" type="text">
            <label for="eventflow-invitation-notes">Organizer notes</label>
            <input id="eventflow-invitation-notes" name="organizer_notes" type="text">
            <button class="button button-secondary" id="eventflow-invitation-submit" type="submit">Create invitation</button>
            <button class="button-link" id="eventflow-invitation-edit-cancel" type="button" hidden>Cancel edit</button>
          </form>
          </details>
          <div class="eventflow-list-filter">
            <div><label for="eventflow-invitation-filter">Filter invitations</label><input autocomplete="off" id="eventflow-invitation-filter" placeholder="Name, email, phone, code, or calling code" type="search"></div>
            <div><label for="eventflow-invitation-state-filter">Invitation state</label><select id="eventflow-invitation-state-filter"><option value="all">All invitations</option><option value="active">Active only</option><option value="archived">Archived only</option></select></div>
            <p aria-live="polite" id="eventflow-invitation-filter-status" role="status"></p>
          </div>
          <p class="description">To review Nigerian contacts, filter by <code>+234</code>. Archive only the contacts you intend to exclude. <code>+1</code> covers both Canada and the United States, so those contacts require individual verification.</p>
          <div id="eventflow-invitation-list"></div>
        </section>
        <section id="eventflow-attendees-panel" role="tabpanel" aria-labelledby="eventflow-attendees-tab" hidden>
          <form class="eventflow-inline-form eventflow-inline-form--wide" id="eventflow-attendee-form">
            <label for="eventflow-attendee-invitation">Invitation</label>
            <select id="eventflow-attendee-invitation" name="invitation_id" required></select>
            <label for="eventflow-attendee-name">Display name</label>
            <input id="eventflow-attendee-name" maxlength="190" name="display_name" required type="text">
            <label for="eventflow-attendee-role">Role</label>
            <select id="eventflow-attendee-role" name="role"><option value="companion">Companion</option><option value="primary">Primary</option></select>
            <label for="eventflow-attendee-email">Email</label>
            <input id="eventflow-attendee-email" name="email" type="email">
            <label for="eventflow-attendee-phone">Phone</label>
            <input id="eventflow-attendee-phone" name="phone" type="tel">
            <label for="eventflow-attendee-dietary">Dietary requirements</label>
            <input id="eventflow-attendee-dietary" name="dietary_requirements" type="text">
            <label for="eventflow-attendee-accessibility">Accessibility requirements</label>
            <input id="eventflow-attendee-accessibility" name="accessibility_requirements" type="text">
            <button class="button button-secondary" type="submit">Add attendee</button>
          </form>
          <div class="eventflow-record-list" id="eventflow-attendee-list"></div>
        </section>
      </section>
      <section class="eventflow-seating" id="eventflow-seating" aria-labelledby="eventflow-seating-title" hidden>
        <div class="eventflow-setup__heading">
          <div>
            <p class="eventflow-admin__eyebrow">Seating</p>
            <h3 id="eventflow-seating-title">Seating workspace</h3>
          </div>
          <button class="button-link" id="eventflow-seating-close" type="button">Close seating workspace</button>
        </div>
        <p class="eventflow-setup__notice" id="eventflow-seating-notice" role="status"></p>
        <div class="eventflow-seating__readiness" id="eventflow-seating-readiness"></div>
        <div class="eventflow-seating__columns">
          <section aria-labelledby="eventflow-tables-title">
            <h4 id="eventflow-tables-title">Tables and seats</h4>
            <form class="eventflow-inline-form eventflow-inline-form--seating" id="eventflow-table-form">
              <label for="eventflow-table-name">Table name</label>
              <input id="eventflow-table-name" maxlength="190" name="name" required type="text">
              <label for="eventflow-table-capacity">Capacity</label>
              <input id="eventflow-table-capacity" max="65535" min="1" name="capacity" required type="number" value="8">
              <label for="eventflow-table-seat-labels">Seat labels</label>
              <input id="eventflow-table-seat-labels" name="seat_labels" placeholder="Optional: 1, 2, 3" type="text">
              <button class="button button-secondary" type="submit">Add table</button>
            </form>
            <div class="eventflow-record-list" id="eventflow-table-list"></div>
          </section>
          <section aria-labelledby="eventflow-groups-title">
            <h4 id="eventflow-groups-title">Seating groups</h4>
            <form class="eventflow-inline-form eventflow-inline-form--seating" id="eventflow-group-form">
              <label for="eventflow-group-name">Group name</label>
              <input id="eventflow-group-name" maxlength="190" name="name" required type="text">
              <label for="eventflow-group-category">Category</label>
              <input id="eventflow-group-category" maxlength="190" name="category" required type="text">
              <label for="eventflow-group-constraint">Constraint</label>
              <select id="eventflow-group-constraint" name="constraint_level"><option value="preferred">Preferred</option><option value="required">Required</option><option value="informational">Informational</option></select>
              <label for="eventflow-group-priority">Priority</label>
              <input id="eventflow-group-priority" max="65535" min="0" name="priority" type="number" value="100">
              <label for="eventflow-group-attendees">Attendee IDs</label>
              <input id="eventflow-group-attendees" name="attendee_ids" placeholder="12, 14, 18" required type="text">
              <button class="button button-secondary" type="submit">Add group</button>
            </form>
            <div class="eventflow-record-list" id="eventflow-group-list"></div>
          </section>
        </div>
        <section class="eventflow-seating__planner" aria-labelledby="eventflow-planner-title">
          <h4 id="eventflow-planner-title">Manual placement</h4>
          <form class="eventflow-inline-form" id="eventflow-placement-form">
            <label for="eventflow-placement-attendee">Attendee</label>
            <select id="eventflow-placement-attendee" name="attendee_id" required></select>
            <label for="eventflow-placement-table">Table</label>
            <select id="eventflow-placement-table" name="table_id" required></select>
            <label for="eventflow-placement-seat">Seat</label>
            <select id="eventflow-placement-seat" name="seat_id"><option value="">Table assignment only</option></select>
            <button class="button button-primary" type="submit">Place attendee</button>
          </form>
        </section>
        <section class="eventflow-seating__recommendation" aria-labelledby="eventflow-recommendation-title">
          <h4 id="eventflow-recommendation-title">Assisted seating</h4>
          <form class="eventflow-inline-form" id="eventflow-recommendation-form">
            <label for="eventflow-recommendation-seed">Plan seed</label>
            <input id="eventflow-recommendation-seed" maxlength="190" name="seed" required type="text" value="organizer-review">
            <button class="button button-secondary" type="submit">Generate recommendation</button>
          </form>
          <div id="eventflow-recommendation-result"></div>
        </section>
      </section>
      <section class="eventflow-reception" id="eventflow-reception" aria-labelledby="eventflow-reception-title" hidden>
        <div class="eventflow-setup__heading">
          <div>
            <p class="eventflow-admin__eyebrow">Event day</p>
            <h3 id="eventflow-reception-title">Reception and check-in</h3>
          </div>
          <button class="button-link" id="eventflow-reception-close" type="button">Close reception workspace</button>
        </div>
        <p class="eventflow-setup__notice" id="eventflow-reception-notice" role="status"></p>
        <form class="eventflow-reception__search" id="eventflow-reception-search-form" role="search">
          <div>
            <label for="eventflow-reception-query">Guest or companion name</label>
            <input id="eventflow-reception-query" maxlength="190" minlength="2" name="q" required type="search">
          </div>
          <div>
            <label for="eventflow-reception-station">Station ID</label>
            <input id="eventflow-reception-station" min="1" name="station_id" placeholder="Optional" type="number">
          </div>
          <div>
            <label for="eventflow-reception-notes">Arrival notes</label>
            <input id="eventflow-reception-notes" maxlength="2000" name="notes" placeholder="Optional" type="text">
          </div>
          <button class="button button-primary eventflow-reception__primary" type="submit">Search attendees</button>
        </form>
        <div class="eventflow-reception__bulk" id="eventflow-reception-bulk" hidden>
          <p id="eventflow-reception-selection">No attendees selected.</p>
          <button class="button button-primary eventflow-reception__primary" id="eventflow-reception-bulk-checkin" type="button">Check in selected</button>
        </div>
        <div class="eventflow-reception__results" id="eventflow-reception-results" aria-labelledby="eventflow-reception-results-title">
          <h4 id="eventflow-reception-results-title">Search results</h4>
          <p class="eventflow-admin__status">Search for a guest to begin reception.</p>
        </div>
      </section>
      <section class="eventflow-communications" id="eventflow-communications" aria-labelledby="eventflow-communications-title" hidden>
        <div class="eventflow-setup__heading">
          <div>
            <p class="eventflow-admin__eyebrow">Communications</p>
            <h3 id="eventflow-communications-title">Email and SMS invitations</h3>
          </div>
          <button class="button-link" id="eventflow-communications-close" type="button">Close communications workspace</button>
        </div>
        <p class="eventflow-setup__notice" id="eventflow-communications-notice" role="status"></p>
        <section class="eventflow-invitation-workspace" aria-labelledby="eventflow-communication-start-title">
          <div class="eventflow-invitation-workspace__intro"><p class="eventflow-admin__eyebrow">Send invitations</p><h4 id="eventflow-communication-start-title">Create, test, and send from one screen</h4><p>Each selected guest receives their own secure RSVP link for attendance and companion names.</p></div>
          <form id="eventflow-invitation-composer">
            <fieldset class="eventflow-invitation-composer__message">
              <legend>1. Create your message</legend>
              <label for="eventflow-invitation-channel">Send by</label><select id="eventflow-invitation-channel" name="channel"><option value="email">Email</option><option value="sms">SMS</option></select>
              <label class="eventflow-invitation-email-field" for="eventflow-invitation-subject">Email subject</label><input class="eventflow-invitation-email-field" id="eventflow-invitation-subject" name="subject" required type="text" value="You're invited to {{event_name}}">
              <label class="eventflow-invitation-email-field" for="eventflow-invitation-image">Invitation-card image</label>
              <div class="eventflow-invitation-image-control eventflow-invitation-email-field"><input id="eventflow-invitation-image" name="image_url" placeholder="Choose an image or paste its Media Library URL" type="url"><button class="button button-secondary" id="eventflow-invitation-image-choose" type="button">Choose image</button></div>
              <label for="eventflow-invitation-message">Message</label><textarea id="eventflow-invitation-message" name="message" required rows="7">Hello {{recipient_name}},

You are warmly invited to {{event_name}}. Open your personalized invitation to confirm attendance and add your companions for seating.

Important: Please confirm your attendance and submit the names of your guest/companions by September 2, 2026, so we can finalize seating.

{{guest_link}}</textarea>
              <p class="description">The guest name, event name, and secure RSVP link are added automatically. The invitation card will also open the guest's RSVP page.</p>
            </fieldset>
            <fieldset class="eventflow-invitation-composer__test">
              <legend>2. Send yourself a test</legend>
              <label for="eventflow-invitation-test-name">Test recipient name</label><input id="eventflow-invitation-test-name" name="test_name" type="text" value="Test Guest">
              <label for="eventflow-invitation-test-address">Your email address</label><input id="eventflow-invitation-test-address" name="test_address" required type="email">
              <button class="button button-secondary" id="eventflow-invitation-test-send" type="button">Send test email</button>
              <p class="description" id="eventflow-invitation-test-status">A test sends only to the address above and never contacts the guest list.</p>
            </fieldset>
            <fieldset class="eventflow-invitation-composer__recipients">
              <legend>3. Choose recipients</legend>
              <div class="eventflow-invitation-recipient-filters">
                <div><label for="eventflow-invitation-recipient-search">Search contacts</label><input id="eventflow-invitation-recipient-search" type="search"></div>
                <div><label for="eventflow-invitation-response-filter">Guest status</label><select id="eventflow-invitation-response-filter"><option value="all">All active guests</option><option value="action_required">Needs a reminder</option><option value="pending">Awaiting RSVP</option><option value="incomplete">Missing companion names</option><option value="accepted">Confirmed</option><option value="declined">Declined</option></select></div>
                <div><label for="eventflow-invitation-phone-region">Phone region</label><select id="eventflow-invitation-phone-region"><option value="all">All regions</option><option value="north_america">Canada/US (+1)</option><option value="international">International (not +1)</option></select></div>
                <div class="eventflow-invitation-recipient-actions"><button class="button button-secondary" id="eventflow-invitation-select-visible" type="button">Select visible</button><button class="button-link" id="eventflow-invitation-clear-selection" type="button">Clear selection</button></div>
              </div>
              <p id="eventflow-invitation-selection-status" role="status">No recipients selected.</p>
              <div class="eventflow-invitation-recipient-list" id="eventflow-invitation-recipient-list"></div>
            </fieldset>
            <section class="eventflow-invitation-review" aria-labelledby="eventflow-invitation-review-title">
              <div><h5 id="eventflow-invitation-review-title">4. Review and send</h5><p id="eventflow-invitation-review-status">Review remains disabled until at least one eligible recipient is selected.</p></div>
              <div class="eventflow-invitation-review__actions"><button class="button button-secondary" id="eventflow-invitation-review" type="button">Review recipients</button><button class="button button-primary" disabled id="eventflow-invitation-send" type="button">Send invitations</button></div>
            </section>
          </form>
        </section>
        <details class="eventflow-communication-advanced" id="eventflow-communication-advanced">
          <summary>Advanced communication records and scheduling</summary>
          <p class="description">Templates, campaigns, scheduling, message status, and retries for administrators.</p>
        <div class="eventflow-people__tabs" role="tablist" aria-label="Communication administration">
          <button aria-controls="eventflow-templates-panel" aria-selected="true" class="button" id="eventflow-templates-tab" role="tab" type="button">Templates</button>
          <button aria-controls="eventflow-campaigns-panel" aria-selected="false" class="button" id="eventflow-campaigns-tab" role="tab" type="button">Campaigns</button>
          <button aria-controls="eventflow-messages-panel" aria-selected="false" class="button" id="eventflow-messages-tab" role="tab" type="button">Messages</button>
        </div>
        <section id="eventflow-templates-panel" role="tabpanel" aria-labelledby="eventflow-templates-tab">
          <form class="eventflow-communication-form" id="eventflow-template-form">
            <label for="eventflow-template-key">Internal template key</label><input id="eventflow-template-key" name="key" required type="text">
            <label for="eventflow-template-name">Message name</label><input id="eventflow-template-name" name="name" required type="text">
            <label for="eventflow-template-channel">Channel</label><select id="eventflow-template-channel" name="channel"><option value="email">Email</option><option value="sms">SMS</option></select>
            <label for="eventflow-template-type">Type</label><input id="eventflow-template-type" name="type" required type="text" value="general">
            <label for="eventflow-template-subject">Subject</label><input id="eventflow-template-subject" name="subject" type="text">
            <label for="eventflow-template-fields">Personalization fields</label><input id="eventflow-template-fields" name="allowed_fields" placeholder="recipient_name, event_name, guest_link" type="text">
            <label class="eventflow-communication-form__wide" for="eventflow-template-body">Body</label><textarea class="eventflow-communication-form__wide" id="eventflow-template-body" name="body" required rows="5"></textarea>
            <label class="eventflow-communication-form__wide" for="eventflow-template-plain">Plain text</label><textarea class="eventflow-communication-form__wide" id="eventflow-template-plain" name="plain_text" rows="3"></textarea>
            <button class="button button-primary" type="submit">Save message draft</button>
          </form>
          <div class="eventflow-record-list" id="eventflow-template-list"></div>
          <div class="eventflow-communication-preview" id="eventflow-template-preview" hidden><h4>Rendered preview</h4><p id="eventflow-template-preview-subject"></p><pre id="eventflow-template-preview-body"></pre><button class="button-link" id="eventflow-template-preview-clear" type="button">Clear preview</button></div>
        </section>
        <section id="eventflow-campaigns-panel" role="tabpanel" aria-labelledby="eventflow-campaigns-tab" hidden>
          <form class="eventflow-communication-form" id="eventflow-campaign-form">
            <label for="eventflow-campaign-template">Published template</label><select id="eventflow-campaign-template" name="template_id" required></select>
            <label for="eventflow-campaign-name">Campaign name</label><input id="eventflow-campaign-name" name="name" required type="text">
            <label for="eventflow-campaign-channel">Channel</label><select id="eventflow-campaign-channel" name="channel"><option value="email">Email</option><option value="sms">SMS</option></select>
            <label for="eventflow-campaign-purpose">Purpose</label><select id="eventflow-campaign-purpose" name="purpose"><option value="invitation">Invitation</option><option value="reminder">Reminder</option><option value="event_update">Event update</option><option value="operational">Operational</option></select>
            <label for="eventflow-campaign-audience-mode">Recipient selection</label><select id="eventflow-campaign-audience-mode" name="audience_mode"><option value="dynamic">All active invitees</option><option value="snapshot">Only the invitation IDs below</option></select>
            <label for="eventflow-campaign-filter">Recipient group</label><select id="eventflow-campaign-filter" name="filter"><option value="active_invitations">Active invitees</option><option value="confirmed_attendees">Confirmed attendees</option></select>
            <label for="eventflow-campaign-invitations">Specific invitation IDs</label><input id="eventflow-campaign-invitations" name="invitation_ids" placeholder="For selected recipients: 3, 8, 12" type="text">
            <button class="button button-primary" type="submit">Create bulk campaign</button>
          </form>
          <div class="eventflow-record-list" id="eventflow-campaign-list"></div>
        </section>
        <section id="eventflow-messages-panel" role="tabpanel" aria-labelledby="eventflow-messages-tab" hidden>
          <form class="eventflow-inline-form" id="eventflow-message-filter-form">
            <label for="eventflow-message-campaign">Campaign ID</label><input id="eventflow-message-campaign" min="1" name="campaign_id" type="number">
            <label for="eventflow-message-status">Status</label><input id="eventflow-message-status" name="status" pattern="[a-z][a-z_]{1,31}" type="text">
            <button class="button button-secondary" type="submit">Filter messages</button>
          </form>
          <div class="eventflow-record-list" id="eventflow-message-list"></div>
          <div class="eventflow-communication-preview" id="eventflow-message-detail" hidden><h4 id="eventflow-message-detail-title">Message detail</h4><p id="eventflow-message-detail-recipient"></p><pre id="eventflow-message-detail-content"></pre><button class="button-link" id="eventflow-message-detail-clear" type="button">Clear message detail</button></div>
        </section>
        </details>
      </section>
      <section class="eventflow-governance" id="eventflow-governance" aria-labelledby="eventflow-governance-title" hidden>
        <div class="eventflow-setup__heading"><div><p class="eventflow-admin__eyebrow">Data and governance</p><h3 id="eventflow-governance-title">Imports, exports, privacy, audit, and diagnostics</h3></div><button class="button-link" id="eventflow-governance-close" type="button">Close data workspace</button></div>
        <p class="eventflow-setup__notice" id="eventflow-governance-notice" role="status"></p>
        <div class="eventflow-people__tabs eventflow-governance__tabs" role="tablist" aria-label="Data and governance administration">
          <button aria-controls="eventflow-imports-panel" aria-selected="true" class="button" id="eventflow-imports-tab" role="tab" type="button">Imports</button>
          <button aria-controls="eventflow-exports-panel" aria-selected="false" class="button" id="eventflow-exports-tab" role="tab" type="button">Exports</button>
          <button aria-controls="eventflow-privacy-panel" aria-selected="false" class="button" id="eventflow-privacy-tab" role="tab" type="button">Privacy</button>
          <button aria-controls="eventflow-audit-panel" aria-selected="false" class="button" id="eventflow-audit-tab" role="tab" type="button">Audit</button>
          <button aria-controls="eventflow-diagnostics-panel" aria-selected="false" class="button" id="eventflow-diagnostics-tab" role="tab" type="button">Diagnostics</button>
        </div>
        <section id="eventflow-imports-panel" role="tabpanel" aria-labelledby="eventflow-imports-tab">
          <form class="eventflow-inline-form" enctype="multipart/form-data" id="eventflow-import-form">
            <label for="eventflow-import-source">CSV or XLSX source</label><input accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" id="eventflow-import-source" name="source" required type="file">
            <button class="button button-primary" type="submit">Stage import</button>
          </form>
          <div class="eventflow-record-list" id="eventflow-import-list"></div>
          <div class="eventflow-governance__detail" id="eventflow-import-detail" hidden><h4>Import rows</h4><pre id="eventflow-import-detail-content"></pre><button class="button-link" id="eventflow-import-detail-clear" type="button">Clear import detail</button></div>
        </section>
        <section id="eventflow-exports-panel" role="tabpanel" aria-labelledby="eventflow-exports-tab" hidden>
          <form class="eventflow-inline-form" id="eventflow-export-form">
            <label for="eventflow-export-type">Export type</label><select id="eventflow-export-type" name="type"><option value="event_summary">Event summary</option><option value="attendees">Attendees</option><option value="invitations">Invitations</option><option value="check_ins">Check-ins</option></select>
            <label for="eventflow-export-format">Format</label><select id="eventflow-export-format" name="format"><option value="csv">CSV</option><option value="jsonl">JSON Lines</option></select>
            <label for="eventflow-export-purpose">Purpose</label><input id="eventflow-export-purpose" maxlength="500" name="purpose" required type="text">
            <button class="button button-primary" type="submit">Request export</button>
          </form>
          <div class="eventflow-record-list" id="eventflow-export-list"></div>
        </section>
        <section id="eventflow-privacy-panel" role="tabpanel" aria-labelledby="eventflow-privacy-tab" hidden>
          <div class="eventflow-seating__columns">
            <form class="eventflow-communication-form" id="eventflow-privacy-action-form"><h4 class="eventflow-communication-form__wide">Request privacy action</h4><label for="eventflow-privacy-invitation">Invitation ID</label><input id="eventflow-privacy-invitation" min="1" name="invitation_id" required type="number"><label for="eventflow-privacy-policy">Policy version</label><input id="eventflow-privacy-policy" name="policy_version" required type="text"><label class="eventflow-communication-form__wide" for="eventflow-privacy-purpose">Purpose</label><input class="eventflow-communication-form__wide" id="eventflow-privacy-purpose" maxlength="500" name="purpose" required type="text"><button class="button button-primary" type="submit">Request privacy action</button></form>
            <form class="eventflow-communication-form" id="eventflow-hold-form"><h4 class="eventflow-communication-form__wide">Place retention hold</h4><label for="eventflow-hold-invitation">Invitation ID</label><input id="eventflow-hold-invitation" min="1" name="invitation_id" placeholder="Optional Event-wide hold" type="number"><label for="eventflow-hold-policy">Policy version</label><input id="eventflow-hold-policy" name="policy_version" required type="text"><label class="eventflow-communication-form__wide" for="eventflow-hold-reason">Reason</label><input class="eventflow-communication-form__wide" id="eventflow-hold-reason" maxlength="500" name="reason" required type="text"><button class="button button-primary" type="submit">Place retention hold</button></form>
          </div>
          <div class="eventflow-seating__columns"><div><h4>Privacy actions</h4><div class="eventflow-record-list" id="eventflow-privacy-action-list"></div></div><div><h4>Retention holds</h4><div class="eventflow-record-list" id="eventflow-hold-list"></div></div></div>
        </section>
        <section id="eventflow-audit-panel" role="tabpanel" aria-labelledby="eventflow-audit-tab" hidden>
          <form class="eventflow-inline-form" id="eventflow-audit-filter-form"><label for="eventflow-audit-action">Action</label><input id="eventflow-audit-action" name="action" type="text"><label for="eventflow-audit-entity">Entity type</label><input id="eventflow-audit-entity" name="entity_type" type="text"><button class="button button-secondary" type="submit">Filter audit history</button><button class="button button-secondary" id="eventflow-audit-integrity" type="button">Verify audit chain</button></form>
          <div class="eventflow-governance__integrity" id="eventflow-audit-integrity-result"></div><div class="eventflow-record-list" id="eventflow-audit-list"></div>
          <div class="eventflow-governance__detail" id="eventflow-audit-detail" hidden><h4>Audit detail</h4><pre id="eventflow-audit-detail-content"></pre><button class="button-link" id="eventflow-audit-detail-clear" type="button">Clear audit detail</button></div>
        </section>
        <section id="eventflow-diagnostics-panel" role="tabpanel" aria-labelledby="eventflow-diagnostics-tab" hidden><p>Diagnostics are sanitized by the server and never include raw logs.</p><button class="button button-secondary" id="eventflow-diagnostics-load" type="button">Load sanitized diagnostics</button><div class="eventflow-governance__detail" id="eventflow-diagnostics-detail" hidden><h4>Diagnostic bundle</h4><pre id="eventflow-diagnostics-content"></pre><button class="button-link" id="eventflow-diagnostics-clear" type="button">Clear diagnostics</button></div></section>
      </section>
    </section>
  </main>
</div>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
