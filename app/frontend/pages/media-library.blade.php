@extends('app')

@section('title', 'Media library — ' . config('app.name'))
@section('page-id', 'media-library')

@section('content')
        <main class="app-content app-content--media-library">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>Media library</h1>
                  <p>Your uploaded media and optional links to free royalty-free sites.</p>
                </div>
              </div>
            </div>
            <div class="head-actions">
              <button type="button" class="btn btn--primary" id="media-upload-btn">
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Upload
              </button>
              <input type="file" id="media-upload-input" accept="image/*,video/*" hidden />
              <button type="button" class="btn btn--ghost" id="media-delete-selected-btn" disabled>
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete selected
              </button>
              <button type="button" class="btn btn--outline btn--danger" id="media-clear-all-btn">
                <i class="fa-solid fa-broom" aria-hidden="true"></i> Clear storage
              </button>
              <a class="btn btn--outline" href="{{ route('composer') }}"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Create post</a>
            </div>
          </div>

          <section class="card card--app-section" aria-labelledby="media-used-heading">
            <div class="card__head card__head--media">
              <h2 id="media-used-heading" class="media-lib-section__title">Your media</h2>
              <div class="segmented media-lib-filter" data-app-media-library role="tablist" aria-label="Filter by type">
                <button type="button" data-media-filter="all" aria-selected="true">All</button>
                <button type="button" data-media-filter="image" aria-selected="false"><i class="fa-regular fa-image" aria-hidden="true"></i> Images</button>
                <button type="button" data-media-filter="video" aria-selected="false"><i class="fa-solid fa-clapperboard" aria-hidden="true"></i> Videos</button>
              </div>
            </div>
            <div class="card__body">
            @php
              $storage = $mediaStorageSummary ?? ['used_bytes' => 0, 'limit_bytes' => 1, 'remaining_bytes' => 0, 'usage_percent' => 0.0, 'limit_mb' => 0];
              $usedMb = round(((int) ($storage['used_bytes'] ?? 0)) / 1048576, 2);
              $remainingMb = round(((int) ($storage['remaining_bytes'] ?? 0)) / 1048576, 2);
              $limitMb = (int) ($storage['limit_mb'] ?? 0);
              $usagePercent = (float) ($storage['usage_percent'] ?? 0.0);
            @endphp
            <div class="media-lib-storage-meter" style="margin-bottom:0.9rem;">
              <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:0.35rem;">
                <strong>Storage usage</strong>
                <span class="prose-muted">{{ number_format($usedMb, 2) }} MB used / {{ number_format((float) $limitMb, 0) }} MB limit ({{ number_format($usagePercent, 1) }}%)</span>
              </div>
              <div style="height:8px; border-radius:999px; background:var(--bg-hover); overflow:hidden;">
                <div style="height:100%; width:{{ max(0, min(100, $usagePercent)) }}%; background:{{ $usagePercent >= 90 ? '#ef4444' : ($usagePercent >= 75 ? '#f59e0b' : '#22c55e') }};"></div>
              </div>
              <small class="prose-muted" style="display:block; margin-top:0.35rem;">Remaining: {{ number_format($remainingMb, 2) }} MB</small>
            </div>
            @if($mediaFiles->isEmpty())
            <div class="empty-lg">
              <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
              <strong>No media yet</strong>
              <span>Upload images and videos to use in your posts.</span>
            </div>
            @else
            <div class="media-lib-grid">
              @foreach($mediaFiles as $file)
              <article class="media-lib-card" data-media-type="{{ $file->type }}" data-media-id="{{ $file->id }}">
                <div class="media-lib-card__thumb{{ $file->type === 'video' ? ' media-lib-card__thumb--video' : '' }}">
                  @if($file->type === 'image')
                  <img src="/{{ $file->path }}" alt="{{ $file->alt_text ?? $file->original_name }}" width="480" height="320" loading="lazy" decoding="async" />
                  @else
                  <img src="/assets/images/video-thumb.svg" alt="{{ $file->original_name }}" width="480" height="320" loading="lazy" decoding="async" />
                  <span class="media-lib-card__play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                  @endif
                  <span class="media-lib-card__badge{{ $file->type === 'video' ? ' media-lib-card__badge--video' : '' }}">{{ ucfirst($file->type) }}</span>
                </div>
                <div class="media-lib-card__meta">
                  <label class="check-line" style="margin-bottom:0.35rem;">
                    <input type="checkbox" data-media-select value="{{ $file->id }}" />
                    <span>Select</span>
                  </label>
                  <span class="media-lib-card__name">{{ $file->original_name }}</span>
                  <span class="media-lib-card__when">Uploaded {{ $file->created_at->diffForHumans() }} · {{ number_format(((int) $file->size_bytes) / 1048576, 2) }} MB</span>
                  <div style="margin-top:0.5rem;">
                    <button type="button" class="btn btn--ghost btn--compact btn--danger" data-media-delete="{{ $file->id }}">Delete</button>
                  </div>
                </div>
              </article>
              @endforeach
            </div>
            @if($mediaFiles->hasPages())
            <div class="media-lib-pagination">
              {{ $mediaFiles->links() }}
            </div>
            @endif
            @endif
            </div>
          </section>

          <section class="card card--app-section" aria-labelledby="media-stock-heading">
            <div class="card__head">
              <h2 id="media-stock-heading" class="media-lib-section__title">Free stock (optional, external)</h2>
            </div>
            <div class="card__body">
            <p class="media-lib-stock-lede">
              If you want to browse outside the library, these sites offer royalty-free assets under their own licenses. {{ config('app.name') }} does not embed or bill for them—follow each site's terms.
            </p>

            <h3 class="media-lib-subhead">Free to use (check license on each site)</h3>
            <div class="stock-market-grid stock-market-grid--free">
              <a class="stock-card stock-card--free" href="https://unsplash.com/" target="_blank" rel="noopener noreferrer">
                <span class="stock-card__icon" aria-hidden="true"><i class="fa-solid fa-images"></i></span>
                <span class="stock-card__name">Unsplash</span>
                <span class="stock-card__hint">High-res photos · Unsplash License</span>
                <span class="stock-card__cta">Browse images <i class="fa-solid fa-arrow-up-right-from-square fa-xs" aria-hidden="true"></i></span>
              </a>
              <a class="stock-card stock-card--free" href="https://www.pexels.com/" target="_blank" rel="noopener noreferrer">
                <span class="stock-card__icon" aria-hidden="true"><i class="fa-solid fa-camera"></i></span>
                <span class="stock-card__name">Pexels</span>
                <span class="stock-card__hint">Photos &amp; videos · Pexels License</span>
                <span class="stock-card__cta">Browse library <i class="fa-solid fa-arrow-up-right-from-square fa-xs" aria-hidden="true"></i></span>
              </a>
              <a class="stock-card stock-card--free" href="https://pixabay.com/" target="_blank" rel="noopener noreferrer">
                <span class="stock-card__icon" aria-hidden="true"><i class="fa-solid fa-film"></i></span>
                <span class="stock-card__name">Pixabay</span>
                <span class="stock-card__hint">Images, video, audio · Pixabay License</span>
                <span class="stock-card__cta">Explore assets <i class="fa-solid fa-arrow-up-right-from-square fa-xs" aria-hidden="true"></i></span>
              </a>
            </div>
            </div>
          </section>
        </main>
@endsection

@push('scripts')
    @php
      $socialAppAsset = 'assets/js/social-app.js';
      $socialAppVersion = file_exists(public_path($socialAppAsset)) ? filemtime(public_path($socialAppAsset)) : time();
    @endphp
    <script src="{{ asset($socialAppAsset) }}?v={{ $socialAppVersion }}"></script>
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        var uploadBtn = document.getElementById("media-upload-btn");
        var fileInput = document.getElementById("media-upload-input");
        var clearBtn = document.getElementById("media-clear-all-btn");
        var deleteSelectedBtn = document.getElementById("media-delete-selected-btn");
        var selectBoxes = document.querySelectorAll("[data-media-select]");

        function selectedIds() {
          var ids = [];
          selectBoxes.forEach(function (cb) {
            if (cb.checked) ids.push(parseInt(cb.value, 10));
          });
          return ids.filter(function (v) { return !isNaN(v) && v > 0; });
        }

        function syncSelectedState() {
          if (!deleteSelectedBtn) return;
          deleteSelectedBtn.disabled = selectedIds().length < 1;
        }

        selectBoxes.forEach(function (cb) {
          cb.addEventListener("change", syncSelectedState);
        });
        syncSelectedState();

        document.querySelectorAll("[data-media-delete]").forEach(function (btn) {
          btn.addEventListener("click", function () {
            var mediaId = btn.getAttribute("data-media-delete");
            if (!mediaId) return;
            if (!confirm("Delete this media file?")) return;
            btn.disabled = true;
            App.apiPost("/media/" + encodeURIComponent(mediaId) + "/delete", {}).then(function (res) {
              if (res && res._ok) {
                App.showFlash("Media deleted.");
                setTimeout(function () { location.reload(); }, 500);
              } else {
                btn.disabled = false;
                App.showFlash((res && (res.message || res.error)) || "Delete failed.", "error");
              }
            }).catch(function () {
              btn.disabled = false;
              App.showFlash("Delete failed.", "error");
            });
          });
        });

        if (deleteSelectedBtn) {
          deleteSelectedBtn.addEventListener("click", function () {
            var ids = selectedIds();
            if (!ids.length) return;
            if (!confirm("Delete " + ids.length + " selected file(s)?")) return;
            deleteSelectedBtn.disabled = true;
            App.apiPost("/media/clear", { ids: ids }).then(function (res) {
              if (res && res._ok) {
                App.showFlash((res.deleted_count || 0) + " file(s) deleted.");
                setTimeout(function () { location.reload(); }, 500);
              } else {
                deleteSelectedBtn.disabled = false;
                App.showFlash((res && (res.message || res.error)) || "Delete failed.", "error");
              }
            }).catch(function () {
              deleteSelectedBtn.disabled = false;
              App.showFlash("Delete failed.", "error");
            });
          });
        }

        if (clearBtn) {
          clearBtn.addEventListener("click", function () {
            if (!confirm("Clear all media from your storage? This cannot be undone.")) return;
            clearBtn.disabled = true;
            App.apiPost("/media/clear", {}).then(function (res) {
              if (res && res._ok) {
                App.showFlash("Storage cleared.");
                setTimeout(function () { location.reload(); }, 500);
              } else {
                clearBtn.disabled = false;
                App.showFlash((res && (res.message || res.error)) || "Could not clear storage.", "error");
              }
            }).catch(function () {
              clearBtn.disabled = false;
              App.showFlash("Could not clear storage.", "error");
            });
          });
        }

        if (uploadBtn && fileInput) {
          uploadBtn.addEventListener("click", function () { fileInput.click(); });
          fileInput.addEventListener("change", function () {
            if (!fileInput.files || !fileInput.files[0]) return;
            var fd = new FormData();
            fd.append("file", fileInput.files[0]);
            uploadBtn.disabled = true;
            App.apiUpload("/media", fd, { label: "Uploading to library…" }).then(function (res) {
              uploadBtn.disabled = false;
              if (res._ok) {
                App.showFlash("File uploaded. Refresh to see it.");
                setTimeout(function () { location.reload(); }, 1200);
              } else {
                App.showFlash(res.message || "Upload failed.", "error");
              }
            }).catch(function () { uploadBtn.disabled = false; App.showFlash("Upload failed.", "error"); });
            fileInput.value = "";
          });
        }
      });
    </script>
@endpush
