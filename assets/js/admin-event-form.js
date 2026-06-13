(function() {
  'use strict';

  function toggleAllDay() {
    var allDay     = document.getElementById('vev_all_day');
    if (!allDay) return;
    var startTime  = document.getElementById('vev_start_time');
    var endTime    = document.getElementById('vev_end_time');
    var note       = document.getElementById('vev_all_day_note');
    if (startTime) startTime.disabled = allDay.checked;
    if (endTime)   endTime.disabled   = allDay.checked;
    if (note)      note.style.display = allDay.checked ? 'block' : 'none';
    updateDatePreview();
  }

  function syncEndDateMin() {
    var startDate = document.getElementById('vev_start_date');
    var startTime = document.getElementById('vev_start_time');
    var endDate   = document.getElementById('vev_end_date');
    var endTime   = document.getElementById('vev_end_time');
    if (!startDate || !endDate) return;
    var startVal = startDate.value;
    if (startVal) {
      endDate.min = startVal;
      if (endDate.value && endDate.value < startVal) endDate.value = startVal;
      if (!endDate.value) endDate.value = startVal;
    }
    if (startDate.value && endDate.value && startDate.value === endDate.value) {
      if (startTime && endTime && startTime.value) {
        endTime.min = startTime.value;
        if (endTime.value && endTime.value < startTime.value) endTime.value = startTime.value;
        if (!endTime.value) endTime.value = startTime.value;
      }
    } else if (endTime) {
      endTime.min = '';
    }
    updateDatePreview();
  }

  function updateDatePreview() {
    var preview = document.getElementById('vev_date_preview');
    if (!preview) return;
    var startDateEl = document.getElementById('vev_start_date');
    var startTimeEl = document.getElementById('vev_start_time');
    var endDateEl   = document.getElementById('vev_end_date');
    var endTimeEl   = document.getElementById('vev_end_time');
    var allDayEl    = document.getElementById('vev_all_day');
    var startDate   = startDateEl ? startDateEl.value : '';
    var startTime   = startTimeEl ? startTimeEl.value : '';
    var endDate     = endDateEl   ? endDateEl.value   : '';
    var endTime     = endTimeEl   ? endTimeEl.value   : '';
    var allDay      = allDayEl    ? allDayEl.checked   : false;
    if (!startDate) {
      preview.innerHTML = '<span style="color:#888;">→ Enter a date</span>';
      return;
    }
    try {
      var locale  = (document.documentElement.lang || 'de-DE').replace(/_/g, '-');
      var dateFmt = new Intl.DateTimeFormat(locale, {weekday:'long', year:'numeric', month:'long', day:'numeric'});
      var timeFmt = new Intl.DateTimeFormat(locale, {hour:'2-digit', minute:'2-digit'});
      var sDate   = new Date(startDate + 'T00:00:00');
      var parts   = [];
      if (allDay) {
        if (endDate && endDate !== startDate) {
          parts.push(dateFmt.format(sDate) + ' – ' + dateFmt.format(new Date(endDate + 'T00:00:00')));
        } else {
          parts.push(dateFmt.format(sDate));
        }
        parts.push('<em style="color:#646970;">(All day)</em>');
      } else {
        parts.push(dateFmt.format(sDate));
        if (startTime) {
          var timeStr = timeFmt.format(new Date(startDate + 'T' + startTime));
          if (endDate || endTime) {
            var eD = endDate || startDate;
            var eT = endTime || startTime;
            timeStr += ' – ';
            if (endDate && endDate !== startDate) {
              timeStr += dateFmt.format(new Date(endDate + 'T00:00:00')) + ' ';
            }
            timeStr += timeFmt.format(new Date(eD + 'T' + eT));
          }
          parts.push('· ' + timeStr);
        }
      }
      preview.innerHTML = parts.join(' ');
    } catch (e) {
      preview.innerHTML = startDate + (startTime ? ' ' + startTime : '');
    }
  }

  document.addEventListener('change', function(e) {
    if (!e.target) return;
    var id = e.target.id;
    if (id === 'vev_all_day') { toggleAllDay(); return; }
    if (id === 'vev_start_date' || id === 'vev_start_time' || id === 'vev_end_date' || id === 'vev_end_time') {
      syncEndDateMin();
    }
  });

  document.addEventListener('DOMContentLoaded', function() {
    toggleAllDay();
    syncEndDateMin();
    updateDatePreview();
  });
})();
