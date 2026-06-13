(function(){
	var data = window.vevSettings || {};

	// Resync computed-meta button.
	var resyncBtn = document.getElementById('vev-resync-meta');
	if (resyncBtn) {
		resyncBtn.addEventListener('click', function(e) {
			e.preventDefault();
			this.disabled = true;
			document.getElementById('vev-resync-result').textContent = data.running || '';
			var body = new URLSearchParams({
				action: 'vev_resync_computed_meta',
				_ajax_nonce: data.resyncNonce || ''
			});
			fetch(ajaxurl, { method: 'POST', body: body })
				.then(function(r) { return r.json(); })
				.then(function(d) {
					document.getElementById('vev-resync-result').textContent =
						d.success ? d.data.count + ' ' + (data.synced || '') : (data.error || '');
				});
		});
	}

	// Settings tabs.
	var tabs     = document.querySelectorAll('#vev-settings-tabs .nav-tab');
	var panels   = document.querySelectorAll('.vev-tab-panel');
	var actions  = document.querySelector('.vev-tab-actions');
	var referer  = document.querySelector('input[name="_wp_http_referer"]');

	if (!tabs.length) return;

	function activate(hash) {
		var target = hash || '#tab-general';
		var anyActive = false;
		tabs.forEach(function(tab){
			var active = tab.getAttribute('href') === target;
			tab.classList.toggle('nav-tab-active', active);
			if(active) anyActive = true;
		});
		if(!anyActive) {
			tabs[0].classList.add('nav-tab-active');
			target = tabs[0].getAttribute('href');
		}
		panels.forEach(function(panel){
			panel.hidden = ('#' + panel.id) !== target;
		});
		if(actions) {
			actions.style.display = (target === '#tab-docs') ? 'none' : '';
		}
	}

	function setRefererTab(tabId) {
		if(!referer) return;
		try {
			var url = new URL(referer.value, location.origin);
			url.searchParams.set('vev_tab', tabId);
			referer.value = url.pathname + url.search;
		} catch(e) {}
	}

	tabs.forEach(function(tab){
		tab.addEventListener('click', function(e){
			e.preventDefault();
			var hash  = tab.getAttribute('href');
			var tabId = hash.replace('#', '');
			history.replaceState(null, '', location.pathname + location.search + hash);
			setRefererTab(tabId);
			activate(hash);
		});
	});

	// Determine initial tab: prefer query param (after save redirect), then hash
	var urlParams = new URLSearchParams(location.search);
	var tabParam  = urlParams.get('vev_tab');
	var initial   = tabParam ? ('#' + tabParam) : (location.hash || '#tab-general');

	activate(initial);
	setRefererTab(initial.replace('#', ''));

	// Clean up the query param from the URL after reading it
	if(tabParam) {
		var clean = new URL(location.href);
		clean.searchParams.delete('vev_tab');
		history.replaceState(null, '', clean.pathname + clean.search + '#' + tabParam);
	}
})();
