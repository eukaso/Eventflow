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
