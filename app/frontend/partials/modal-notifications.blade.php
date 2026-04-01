<div class="app-modal app-modal--cool" id="modal-notifications" data-app-modal role="dialog" aria-modal="true" aria-labelledby="modal-notifications-title" aria-hidden="true">
  <div class="app-modal__backdrop" data-app-modal-close tabindex="-1" aria-hidden="true"></div>
  <div class="app-modal__panel app-modal__panel--notifications">
    <div class="app-modal__head">
      <div>
        <h2 id="modal-notifications-title">Notifications</h2>
        <p class="app-modal__lede">Recent activity across your workspace.</p>
      </div>
      <button type="button" class="icon-btn" data-app-modal-close aria-label="Close dialog">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>
    <div class="app-modal__body app-modal__body--notifications">
      <ul class="modal-notif-list" role="list">
        <li class="modal-notif-item modal-notif-item--placeholder">
          <span class="modal-notif-item__icon" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>
          <div class="modal-notif-item__body">
            <span>Loading notifications…</span>
          </div>
        </li>
      </ul>
    </div>
    <div class="app-modal__foot">
      <button type="button" class="btn btn--ghost" data-app-modal-close>Close</button>
    </div>
  </div>
</div>
