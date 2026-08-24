<?php

namespace EventFlow\Presentation\Guest;

use EventFlow\Bootstrap\BootstrapResult;

final readonly class GuestShellView
{
    public function render(BootstrapResult $bootstrap): string
    {
        $state = htmlspecialchars($bootstrap->state->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ready = $bootstrap->ready ? 'true' : 'false';

        return <<<HTML
<div class="eventflow-guest" id="eventflow-guest" data-bootstrap-state="{$state}" data-ready="{$ready}">
  <main class="eventflow-guest__card" aria-busy="true" id="eventflow-guest-region">
    <p class="eventflow-guest__eyebrow">Your official invitation</p>
    <h1 id="eventflow-guest-title">Your invitation</h1>
    <p class="eventflow-guest__status" id="eventflow-guest-status" role="status">Opening your secure invitation…</p>
    <section id="eventflow-guest-context" aria-labelledby="eventflow-guest-title" hidden>
      <p class="eventflow-guest__salutation" id="eventflow-guest-salutation"></p>
      <p class="eventflow-guest__welcome" id="eventflow-guest-welcome"></p>
      <dl class="eventflow-guest__facts" id="eventflow-guest-facts"></dl>
      <p class="eventflow-guest__notice" id="eventflow-guest-notice"></p>
      <form id="eventflow-rsvp-form">
        <div class="eventflow-guest__step-heading">
          <span aria-hidden="true">1</span>
          <div><h2>Confirm your attendance</h2><p>Let your host know whether you can celebrate with them.</p></div>
        </div>
        <fieldset class="eventflow-guest__choice">
          <legend class="screen-reader-text">Will you attend?</legend>
          <label><input name="response_status" required type="radio" value="accepted"> Joyfully accept</label>
          <label><input name="response_status" required type="radio" value="declined"> Regretfully decline</label>
        </fieldset>
        <section aria-labelledby="eventflow-attendee-heading" id="eventflow-guest-attendees">
          <div class="eventflow-guest__step-heading">
            <span aria-hidden="true">2</span>
            <div><h2 id="eventflow-attendee-heading">Tell us who is coming</h2><p id="eventflow-party-guidance">Add the names of everyone attending with you for seating.</p></div>
          </div>
          <div class="eventflow-guest__party-actions"><p id="eventflow-party-capacity"></p><button class="eventflow-guest__link" id="eventflow-add-guest" type="button">Add companion</button></div>
          <div id="eventflow-guest-attendee-list"></div>
        </section>
        <button class="eventflow-guest__primary" type="submit">Confirm my RSVP</button>
      </form>
      <p class="eventflow-guest__confirmation" id="eventflow-guest-confirmation" role="status"></p>
      <button class="eventflow-guest__link" id="eventflow-guest-logout" type="button" hidden>Close this secure session</button>
    </section>
  </main>
</div>
HTML;
    }
}
