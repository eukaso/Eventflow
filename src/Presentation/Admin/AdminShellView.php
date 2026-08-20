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
          <div class="eventflow-record-list" id="eventflow-invitation-list"></div>
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
