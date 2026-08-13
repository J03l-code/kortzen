/**
 * KORTZEN - Calendar Helper
 * Genera enlaces directos a Google Calendar y archivos .ics para Apple Calendar / Outlook
 */

window.KortzenCalendar = {
    addToGoogleCalendar: function (title, details, location, fechaHoraStr, duracionMinutos = 30) {
        const start = new Date(fechaHoraStr);
        const end = new Date(start.getTime() + duracionMinutos * 60000);

        const formatTime = (d) => d.toISOString().replace(/-|:|\.\d+/g, '');

        const url = `https://calendar.google.com/calendar/render?action=TEMPLATE` +
            `&text=${encodeURIComponent(title)}` +
            `&details=${encodeURIComponent(details)}` +
            `&location=${encodeURIComponent(location)}` +
            `&dates=${formatTime(start)}/${formatTime(end)}`;

        window.open(url, '_blank');
    },

    downloadIcs: function (title, details, location, fechaHoraStr, duracionMinutos = 30) {
        const start = new Date(fechaHoraStr);
        const end = new Date(start.getTime() + duracionMinutos * 60000);

        const formatTime = (d) => d.toISOString().replace(/-|:|\.\d+/g, '');

        const icsData =
            `BEGIN:VCALENDAR\n` +
            `VERSION:2.0\n` +
            `PRODID:-//KORTZEN Barberia//ES\n` +
            `BEGIN:VEVENT\n` +
            `SUMMARY:${title}\n` +
            `DESCRIPTION:${details}\n` +
            `LOCATION:${location}\n` +
            `DTSTART:${formatTime(start)}\n` +
            `DTEND:${formatTime(end)}\n` +
            `STATUS:CONFIRMED\n` +
            `END:VEVENT\n` +
            `END:VCALENDAR`;

        const blob = new Blob([icsData], { type: 'text/calendar;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.setAttribute('download', 'cita-kortzen.ics');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
};
