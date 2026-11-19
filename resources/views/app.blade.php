<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="theme-color" content="#059669" />
    <title>Budget Choice Bd - Business Hub (Live Sync v1.0.1)</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}" />
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" />
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}" />
    <script>
      (function() {
        var originalFetch = window.fetch;
        window.fetch = function(input, init) {
          init = init || {};
          init.headers = init.headers || {};
          try {
            var token = localStorage.getItem('biz_cpanel_auth_token');
            if (token) {
              if (typeof Headers !== 'undefined' && init.headers instanceof Headers) {
                if (!init.headers.has('X-Auth-Token')) init.headers.set('X-Auth-Token', token);
                if (!init.headers.has('Authorization')) init.headers.set('Authorization', 'Bearer ' + token);
              } else {
                if (!init.headers['X-Auth-Token']) init.headers['X-Auth-Token'] = token;
                if (!init.headers['Authorization']) init.headers['Authorization'] = 'Bearer ' + token;
              }
            }
          } catch (e) {}
          return originalFetch.call(this, input, init);
        };
      })();
    </script>
    @php
      $cssFiles = glob(public_path('assets/index-*.css')) ?: [];
      $jsFiles = glob(public_path('assets/index-*.js')) ?: [];
    @endphp
    @foreach ($cssFiles as $cssFile)
      <link rel="stylesheet" href="{{ asset('assets/' . basename($cssFile)) }}" />
    @endforeach
  </head>
  <body class="bg-neutral-50 text-neutral-900 antialiased">
    <div id="root"></div>
    @foreach ($jsFiles as $jsFile)
      <script type="module" src="{{ asset('assets/' . basename($jsFile)) }}"></script>
    @endforeach

    <!-- Live Multi-Courier Fraud Network Checker Integration -->
    <script>
      (function() {
        var lastCheckedPhone = '';
        var checkTimeout = null;

        function cleanPhone(p) {
          if (!p) return '';
          var s = ('' + p).replace(/[^0-9]/g, '');
          if (s.indexOf('880') === 0) s = s.substring(2);
          else if (s.indexOf('88') === 0) s = s.substring(2);
          return s;
        }

        function renderFraudBox(container, data, loading, error) {
          var box = document.getElementById('bd-courier-fraud-box');
          if (!box) {
            box = document.createElement('div');
            box.id = 'bd-courier-fraud-box';
            box.className = 'mt-3 mb-3';
            container.parentNode.insertBefore(box, container.nextSibling);
          }

          if (loading) {
            box.innerHTML = '<div class="p-3.5 bg-neutral-50 rounded-xl border border-neutral-200 text-neutral-600 text-xs flex items-center gap-3 animate-pulse">' +
              '<div class="w-4 h-4 border-2 border-emerald-600 border-t-transparent rounded-full animate-spin shrink-0"></div>' +
              '<div><span class="font-bold text-neutral-800">বাংলাদেশ কুরিয়ার ফ্রড নেটওয়ার্ক সার্চ হচ্ছে...</span><p class="text-[10px] text-neutral-400 mt-0.5">Pathao, Steadfast, RedX, Paperfly, Carrybee থেকে রেকর্ড সংগ্রহ করা হচ্ছে</p></div>' +
              '</div>';
            return;
          }

          if (error) {
            box.innerHTML = '<div class="p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs">' + error + '</div>';
            return;
          }

          if (!data || !data.success) {
            box.innerHTML = '';
            return;
          }

          var agg = data.aggregate || {};
          var risk = data.risk || {};
          var couriers = data.couriers || {};
          var pathao = couriers.pathao || {};
          var steadfast = couriers.steadfast || {};

          var riskBg = risk.color === 'rose' ? 'bg-rose-50 border-rose-300 text-rose-900' :
                       risk.color === 'amber' ? 'bg-amber-50 border-amber-300 text-amber-900' :
                       risk.color === 'blue' ? 'bg-blue-50 border-blue-200 text-blue-900' :
                       'bg-emerald-50 border-emerald-300 text-emerald-900';

          var rateColor = (agg.success_ratio || 0) < 60 ? 'text-rose-600' :
                          (agg.success_ratio || 0) < 80 ? 'text-amber-600' : 'text-emerald-600';

          var html = '<div class="p-4 rounded-xl border shadow-xs space-y-3 ' + riskBg + '">' +
            '<div class="flex items-center justify-between">' +
              '<div class="flex items-center gap-2">' +
                '<span class="text-base">🛡️</span>' +
                '<div>' +
                  '<div class="text-xs font-bold leading-tight uppercase tracking-wider">BD Courier Fraud Network Report</div>' +
                  '<div class="text-[11px] font-bold mt-0.5">' + (risk.title || 'কুরিয়ার রিপোর্ট') + '</div>' +
                '</div>' +
              '</div>' +
              '<span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border ' + (risk.badge || 'bg-neutral-100 text-neutral-800') + '">' +
                (risk.level === 'safe' ? 'Verified Safe' : risk.level === 'high' ? 'High Risk' : risk.level === 'medium' ? 'Moderate' : 'New') +
              '</span>' +
            '</div>' +

            '<div class="grid grid-cols-3 gap-2 text-center text-xs">' +
              '<div class="bg-white/90 p-2 rounded-lg border border-neutral-200/80 shadow-2xs">' +
                '<div class="text-[10px] text-neutral-500 font-bold uppercase">মোট ডেলিভারি</div>' +
                '<div class="text-sm font-bold font-mono text-neutral-900 mt-0.5">' + (agg.total_deliveries || 0) + ' টি</div>' +
              '</div>' +
              '<div class="bg-white/90 p-2 rounded-lg border border-neutral-200/80 shadow-2xs">' +
                '<div class="text-[10px] text-neutral-500 font-bold uppercase">সফল ডেলিভারি</div>' +
                '<div class="text-sm font-bold font-mono ' + rateColor + ' mt-0.5">' + (agg.success_ratio || 0) + '%</div>' +
              '</div>' +
              '<div class="bg-white/90 p-2 rounded-lg border border-neutral-200/80 shadow-2xs">' +
                '<div class="text-[10px] text-neutral-500 font-bold uppercase">রিটার্ন / বাতিল</div>' +
                '<div class="text-sm font-bold font-mono ' + ((agg.cancel_ratio || 0) > 20 ? 'text-rose-600' : 'text-neutral-700') + ' mt-0.5">' + (agg.cancel_ratio || 0) + '%</div>' +
              '</div>' +
            '</div>' +

            '<div class="space-y-1.5 pt-1 border-t border-neutral-200/60 text-xs">' +
              '<div class="text-[10px] font-bold text-neutral-600 uppercase tracking-wider">কুরিয়ারভিত্তিক রেকর্ড:</div>' +
              '<div class="flex flex-wrap gap-1.5">' +
                (pathao.total !== undefined ?
                  '<div class="bg-white/90 px-2 py-1 rounded-md border border-neutral-200 text-[11px] flex items-center gap-1.5 font-medium">' +
                    '<span class="w-2 h-2 rounded-full bg-emerald-500"></span>' +
                    '<strong>Pathao:</strong> ' + pathao.success + ' ডেলিভার্ড, ' + pathao.cancel + ' রিটার্ন (' + pathao.success_ratio + '%)' +
                  '</div>' : '') +
                (steadfast.success !== undefined ?
                  '<div class="bg-white/90 px-2 py-1 rounded-md border border-neutral-200 text-[11px] flex items-center gap-1.5 font-medium">' +
                    '<span class="w-2 h-2 rounded-full bg-blue-500"></span>' +
                    '<strong>Steadfast:</strong> ' + steadfast.success + ' ডেলিভার্ড, ' + (steadfast.cancel || 0) + ' রিটার্ন' +
                  '</div>' : '') +
              '</div>' +
            '</div>' +

            '<div class="p-2.5 bg-white/90 rounded-lg border border-neutral-200 text-[11px] leading-relaxed text-neutral-800">' +
              '💡 <strong>পরামর্শ:</strong> ' + (risk.recommendation || '') +
            '</div>' +
          '</div>';

          box.innerHTML = html;
        }

        function handlePhoneChange(input) {
          var raw = input.value || '';
          var phone = cleanPhone(raw);
          if (phone.length === 11 && /^01[3-9]\d{8}$/.test(phone)) {
            if (phone === lastCheckedPhone) return;
            lastCheckedPhone = phone;
            if (checkTimeout) clearTimeout(checkTimeout);
            checkTimeout = setTimeout(function() {
              var inputParent = input.closest('div.relative') ? input.closest('div.relative').parentNode : input.parentNode;
              renderFraudBox(inputParent, null, true, null);
              fetch('/api/fraud-check?phone=' + encodeURIComponent(phone))
                .then(function(res) { return res.json(); })
                .then(function(data) {
                  renderFraudBox(inputParent, data, false, null);
                })
                .catch(function() {
                  renderFraudBox(inputParent, null, false, 'কুরিয়ার সার্ভার থেকে তথ্য পাওয়া যায়নি।');
                });
            }, 300);
          } else {
            lastCheckedPhone = '';
            var box = document.getElementById('bd-courier-fraud-box');
            if (box) box.innerHTML = '';
          }
        }

        function scanForModal() {
          var inputs = document.querySelectorAll('input[type="tel"][placeholder="01XXXXXXXXX"]');
          inputs.forEach(function(input) {
            if (!input.dataset.fraudHooked) {
              input.dataset.fraudHooked = 'true';
              input.addEventListener('input', function() { handlePhoneChange(input); });
              input.addEventListener('paste', function() { setTimeout(function() { handlePhoneChange(input); }, 50); });
              if (input.value) {
                handlePhoneChange(input);
              }
            }
          });
        }

        function scanForCourierSync() {
          // Check if we are viewing the Courier page
          var isCourierPage = document.querySelector('button[type="button"]') && (
            document.body.innerText.indexOf('Courier Integration') !== -1 ||
            document.body.innerText.indexOf('Pathao Courier') !== -1 ||
            document.body.innerText.indexOf('Dispatch New Parcel') !== -1 ||
            window.location.hash.indexOf('courier') !== -1
          );

          var existingBtn = document.getElementById('pathao-sync-btn');
          if (!isCourierPage) {
            if (existingBtn) existingBtn.remove();
            return;
          }

          if (existingBtn) return;

          // Find suitable toolbar or header container
          var targetContainer = document.querySelector('header') || document.querySelector('nav') || document.body;
          var floatBar = document.createElement('div');
          floatBar.id = 'pathao-sync-btn';
          floatBar.className = 'fixed bottom-5 right-5 z-40 flex items-center gap-2 bg-neutral-900/90 hover:bg-neutral-900 backdrop-blur text-white px-4 py-2.5 rounded-2xl shadow-xl border border-neutral-700/60 cursor-pointer transition-all duration-200 select-none';
          floatBar.innerHTML = '<span id="pathao-sync-icon" class="text-base">🔄</span>' +
            '<div class="text-left">' +
              '<div class="text-[11px] font-bold tracking-tight">Sync Pathao Orders</div>' +
              '<div id="pathao-sync-sub" class="text-[9px] text-neutral-400">পাঠাও থেকে কাস্টমার লিস্ট আনুন</div>' +
            '</div>';

          floatBar.addEventListener('click', function() {
            var icon = document.getElementById('pathao-sync-icon');
            var sub = document.getElementById('pathao-sync-sub');
            if (floatBar.dataset.loading === 'true') return;
            floatBar.dataset.loading = 'true';
            if (icon) icon.className = 'text-base inline-block animate-spin';
            if (sub) sub.innerText = 'অর্ডার লোড হচ্ছে...';

            fetch('/api/courier/pathao/import-parcels', { method: 'POST' })
              .then(function(res) { return res.json(); })
              .then(function(data) {
                if (data.success) {
                  if (icon) icon.className = 'text-base text-emerald-400';
                  if (sub) sub.innerHTML = '<span class="text-emerald-400 font-bold">' + data.total_synced + 'টি অর্ডার সিঙ্ক হয়েছে! রিফ্রেশ হচ্ছে...</span>';
                  setTimeout(function() {
                    window.location.reload();
                  }, 1200);
                } else {
                  alert(data.error || 'Pathao sync failed');
                  if (icon) icon.className = 'text-base';
                  if (sub) sub.innerText = 'আবার চেষ্টা করুন';
                  floatBar.dataset.loading = 'false';
                }
              })
              .catch(function(err) {
                alert('কুরিয়ার সার্ভারের সাথে সংযোগ ব্যর্থ হয়েছে।');
                if (icon) icon.className = 'text-base';
                if (sub) sub.innerText = 'ত্রুটি হয়েছে';
                floatBar.dataset.loading = 'false';
              });
          });

          document.body.appendChild(floatBar);
        }

        var observer = new MutationObserver(function() {
          scanForModal();
          scanForCourierSync();
        });
        observer.observe(document.body, { childList: true, subtree: true });
        scanForModal();
        scanForCourierSync();
      })();
    </script>
  </body>
</html>
