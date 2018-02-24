<!doctype html>
<html dir="{{ $direction }}" lang="{{ $language }}">
  <!--


    TO SEND DATA INTO THIS TEMPLATE, USE:
         WebAppView.php



  -->
  <head>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-105369895-3"></script>

    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'UA-105369895-3');
      window.flarum_autologin_referer = '{{ $referer }}';
    </script>

    <!-- Load Proxima Nova font -->
    <script src="https://use.typekit.net/hhx1otp.js"></script>
    <script>try{Typekit.load({ async: true });}catch(e){}</script>

    <!-- Mithril-0.2.x-based drag&drop -->
    <script src="//cdnjs.cloudflare.com/ajax/libs/dragula/3.1.0/dragula.min.js"></script>
  
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1">
    <meta name="theme-color" content="{{ array_get($forum, 'attributes.themePrimaryColor') }}">
    @if (! $allowJs)
      <meta name="robots" content="noindex" />
    @endif

    @foreach ($cssUrls as $url)
      <link rel="stylesheet" href="{{ $url }}">
    @endforeach

    @if ($faviconUrl = array_get($forum, 'attributes.faviconUrl'))
      <link href="{{ $faviconUrl }}" rel="shortcut icon">
    @endif

    {!! $head !!}
  </head>

  <body>
    {!! $layout !!}

    <div id="modal"></div>
    <div id="alerts"></div>

    @if ($allowJs)
      <script>
        document.getElementById('flarum-loading').style.display = 'block';
      </script>

      @foreach ($jsUrls as $url)
        <script src="{{ $url }}"></script>
      @endforeach

      <script>
        document.getElementById('flarum-loading').style.display = 'none';
        @if (! $debug)
        try {
        @endif
          var app = System.get('flarum/app').default;
          var modules = {!! json_encode($modules) !!};

          for (var i in modules) {
            var module = System.get(modules[i]);
            if (module.default) module.default(app);
          }

          app.boot({!! json_encode($payload, JSON_HEX_TAG) !!});
        @if (! $debug)
        } catch (e) {
          window.location += (window.location.search ? '&' : '?') + 'nojs=1';
          throw e;
        }
        @endif
      </script>
    @else
      <script>
        window.history.replaceState(null, null, window.location.toString().replace(/([&?]nojs=1$|nojs=1&)/, ''));
      </script>
    @endif

    {!! $foot !!}
  </body>
</html>
