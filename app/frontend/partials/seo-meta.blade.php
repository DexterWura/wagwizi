@php
  $defaults = is_array($seoDefaults ?? null) ? $seoDefaults : [];

  $siteName = trim((string) ($seoSiteNameOverride ?? ($defaults['site_name'] ?? config('app.name'))));
  $seoTitle = trim((string) ($seoTitleOverride ?? $__env->yieldContent('title', $defaults['meta_title'] ?? $siteName)));
  $seoDescription = trim((string) ($seoDescriptionOverride ?? $__env->yieldContent('meta-description', $defaults['meta_description'] ?? '')));
  $seoSocialDescription = trim((string) ($seoSocialDescriptionOverride ?? $__env->yieldContent('social-description', $defaults['social_description'] ?? $seoDescription)));
  $seoKeywords = trim((string) ($seoKeywordsOverride ?? $__env->yieldContent('meta-keywords', $defaults['keywords'] ?? '')));
  $seoType = trim((string) ($seoTypeOverride ?? $__env->yieldContent('og-type', $defaults['type'] ?? 'website')));
  $seoRobots = trim((string) ($seoRobotsOverride ?? $__env->yieldContent('seo-robots', $defaults['robots'] ?? 'index,follow')));
  $seoCanonical = trim((string) ($seoCanonicalOverride ?? $__env->yieldContent('canonical-url', url()->current())));
  $seoImage = trim((string) ($seoImageOverride ?? $__env->yieldContent('social-image', $defaults['image_url'] ?? '')));
  $seoTwitterSite = trim((string) ($seoTwitterSiteOverride ?? ($defaults['twitter_site'] ?? '')));
  $seoFavicon = trim((string) ($seoFaviconOverride ?? ($defaults['favicon_url'] ?? '')));
  $tagline = trim((string) ($defaults['tagline'] ?? ''));

  if ($seoTitle === '') {
    $seoTitle = $siteName;
  }
  if (request()->routeIs('landing')) {
    if ($tagline !== '') {
      $seoTitle = $siteName . ' | ' . $tagline;
    }
  } else {
    $pagePart = $seoTitle;
    if ($siteName !== '') {
      $escapedSite = preg_quote($siteName, '/');
      $pagePart = preg_replace('/\s*[|:\-—]\s*' . $escapedSite . '\s*$/iu', '', $pagePart) ?? $pagePart;
      $pagePart = preg_replace('/^' . $escapedSite . '\s*[|:\-—]\s*/iu', '', $pagePart) ?? $pagePart;
      if (strcasecmp(trim($pagePart), $siteName) === 0) {
        $pagePart = '';
      }
    }
    $pagePart = trim((string) $pagePart);
    $seoTitle = $pagePart !== '' ? ($siteName . ' | ' . $pagePart) : $siteName;
  }
  if ($seoDescription === '') {
    $seoDescription = $defaults['meta_description'] ?? '';
  }
  if ($seoSocialDescription === '') {
    $seoSocialDescription = $seoDescription;
  }
  if ($seoCanonical === '') {
    $seoCanonical = url()->current();
  }

  $twitterCard = $seoImage !== '' ? 'summary_large_image' : 'summary';
@endphp
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}" />
@if($seoKeywords !== '')
<meta name="keywords" content="{{ $seoKeywords }}" />
@endif
<meta name="robots" content="{{ $seoRobots }}" />
<link rel="canonical" href="{{ $seoCanonical }}" />
@if($seoFavicon !== '')
<link rel="icon" href="{{ $seoFavicon }}" />
<link rel="shortcut icon" href="{{ $seoFavicon }}" />
<link rel="apple-touch-icon" href="{{ $seoFavicon }}" />
@endif

<meta property="og:type" content="{{ $seoType !== '' ? $seoType : 'website' }}" />
<meta property="og:site_name" content="{{ $siteName }}" />
<meta property="og:title" content="{{ $seoTitle }}" />
<meta property="og:description" content="{{ $seoSocialDescription }}" />
<meta property="og:url" content="{{ $seoCanonical }}" />
@if($seoImage !== '')
<meta property="og:image" content="{{ $seoImage }}" />
@endif

<meta name="twitter:card" content="{{ $twitterCard }}" />
<meta name="twitter:title" content="{{ $seoTitle }}" />
<meta name="twitter:description" content="{{ $seoSocialDescription }}" />
@if($seoTwitterSite !== '')
<meta name="twitter:site" content="{{ $seoTwitterSite }}" />
@endif
@if($seoImage !== '')
<meta name="twitter:image" content="{{ $seoImage }}" />
@endif

@if(stripos($seoRobots, 'noindex') === false)
<script type="application/ld+json">
{!! json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'WebSite',
  'name' => $siteName,
  'url' => rtrim((string) config('app.url'), '/'),
  'description' => $seoDescription,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endif

