@extends('app')

@section('title', 'All posts — ' . config('app.name'))
@section('page-id', 'posts-index')

@section('content')
        <main class="app-content app-content--posts-index">
          <div class="page-head">
            <div class="page-head__row">
              <div class="page-head__title">
                <div class="page-icon" aria-hidden="true">
                  <i class="fa-solid fa-list-ul" aria-hidden="true"></i>
                </div>
                <div>
                  <h1>All posts</h1>
                  <p>Drafts, scheduled, and published — filter, search, and open a draft in the composer.</p>
                </div>
              </div>
              <div class="head-actions">
                <a class="btn btn--primary" href="{{ route('composer') }}"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> New post</a>
              </div>
            </div>
          </div>

          <section class="card card--app-section posts-index-filters" aria-label="Filters">
            <div class="card__body posts-index-filters__body">
              <div class="field posts-index-filters__search">
                <label class="field__label" for="posts-index-q">Search</label>
                <input class="input" type="search" id="posts-index-q" data-posts-index-q placeholder="Search post text…" autocomplete="off" />
              </div>
              <div class="field">
                <label class="field__label" for="posts-index-status">Status</label>
                <select class="select" id="posts-index-status" data-posts-index-status>
                  <option value="all" selected>All statuses</option>
                  <option value="draft">Draft</option>
                  <option value="scheduled">Scheduled</option>
                  <option value="published">Published</option>
                  <option value="failed">Failed</option>
                  <option value="queued">Queued</option>
                  <option value="publishing">Publishing</option>
                </select>
              </div>
              <div class="field">
                <label class="field__label" for="posts-index-sort">Sort</label>
                <select class="select" id="posts-index-sort" data-posts-index-sort>
                  <option value="newest" selected>Newest first</option>
                  <option value="scheduled">Soonest scheduled</option>
                </select>
              </div>
            </div>
          </section>

          <section class="card card--app-section" aria-label="Post list" data-posts-index-root>
            <div class="card__head card__head--posts-index">
              <h2 class="posts-index-list__title">Posts</h2>
              <span class="posts-index-count prose-muted" data-posts-index-count hidden></span>
            </div>
            <div class="card__body">
              <p class="posts-index-loading prose-muted" data-posts-index-loading>Loading…</p>
              <p class="posts-index-empty empty-lg" data-posts-index-empty hidden>No posts match your filters.</p>
              <ul class="posts-index-list" data-posts-index-list hidden></ul>
              <nav class="posts-index-pagination" data-posts-index-pagination hidden aria-label="Pagination"></nav>
            </div>
          </section>
        </main>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/posts-index.js') }}"></script>
@endpush
