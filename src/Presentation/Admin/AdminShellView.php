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
