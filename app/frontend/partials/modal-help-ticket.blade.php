<div class="app-modal app-modal--fab-ticket" id="modal-help-ticket" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-help-ticket-title" aria-hidden="true">
  <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
  <div class="app-modal__panel">
    <div class="app-modal__head">
      <div>
        <h2 id="modal-help-ticket-title">Create support ticket</h2>
        <p class="app-modal__lede">We typically reply within one business day. You will get email updates.</p>
      </div>
      <button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>
    <div class="app-modal__body">
      <div class="field">
        <label class="field__label" for="ticket-subject">Subject</label>
        <input class="input" id="ticket-subject" name="ticket-subject" type="text" placeholder="Brief summary" autocomplete="off" />
      </div>
      <div class="field">
        <label class="field__label" for="ticket-category">Category</label>
        <select class="select" id="ticket-category" name="ticket-category">
          <option value="publishing">Publishing &amp; scheduling</option>
          <option value="accounts">Accounts &amp; connections</option>
          <option value="technical">Technical / API</option>
          <option value="billing">Billing</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="field">
        <label class="field__label" for="ticket-message">Details</label>
        <textarea class="textarea" id="ticket-message" name="ticket-message" placeholder="What happened? Include steps, links, or error IDs." rows="5"></textarea>
      </div>
      <label class="check-line">
        <input type="checkbox" name="ticket-context" checked />
        <span>Attach current page URL and browser info to this ticket</span>
      </label>
    </div>
    <div class="app-modal__foot">
      <button type="button" class="btn btn--ghost" data-app-modal-close>Cancel</button>
      <button type="button" class="btn btn--primary" data-app-modal-close>Submit ticket</button>
    </div>
  </div>
</div>
