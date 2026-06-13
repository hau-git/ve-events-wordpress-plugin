(function($) {
	var data = window.vevSeriesSuggestion || {};

	function vevSeriesAjax(action, termId) {
		var postId = $('#vev-series-create').data('post-id');
		var nonce  = $('#vev-series-create').data('nonce');
		$.post(ajaxurl, {
			action:  'vev_series_suggestion',
			sub:     action,
			post_id: postId,
			term_id: termId || 0,
			nonce:   nonce
		}, function(response) {
			if (response.success) {
				$('#vev-series-suggestion').slideUp();
			} else {
				$('#vev-series-feedback').html('<span style="color:#d63638;">' + (response.data || data.errorRetry || '') + '</span>');
			}
		});
	}
	$('#vev-series-create').on('click', function() { vevSeriesAjax('create'); });
	$('#vev-series-dismiss').on('click', function() { vevSeriesAjax('dismiss'); });
	$('#vev-series-assign').on('click', function() {
		var termId = $('#vev-series-existing-select').val();
		if (!termId) { alert(data.selectFirst || ''); return; }
		vevSeriesAjax('assign', termId);
	});
})(jQuery);
