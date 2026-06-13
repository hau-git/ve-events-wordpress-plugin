(function() {
	var rows = document.querySelectorAll('#the-list tr');
	if (!rows.length) return;
	var locale   = (document.documentElement.lang || 'de-DE').replace(/_/g, '-');
	var monthFmt = new Intl.DateTimeFormat(locale, {month:'long', year:'numeric'});
	var lastMonth = null;
	rows.forEach(function(row) {
		var span = row.querySelector('.vev-when-date[data-vev-month]');
		if (!span) return;
		var month = span.getAttribute('data-vev-month');
		if (month === lastMonth) return;
		lastMonth = month;
		var p   = month.split('-');
		var d   = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, 1);
		var sep = document.createElement('tr');
		sep.className = 'vev-month-separator';
		var cols = row.querySelectorAll('td, th').length || 1;
		sep.innerHTML = '<td colspan="' + cols + '">' + monthFmt.format(d) + '</td>';
		row.parentNode.insertBefore(sep, row);
	});
})();
