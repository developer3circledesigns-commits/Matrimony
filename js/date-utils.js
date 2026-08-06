function formatDateForDisplay(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    return `${day}-${month}-${year}`;
}

function formatDateForInput(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    
    return `${year}-${month}-${day}`;
}

function updateProfileDateDisplay() {
    document.querySelectorAll('[data-testid="profile-date"], [data-profile-date], [data-date-display]').forEach(function(element) {
        const dateString = element.getAttribute('data-date') || element.getAttribute('data-date-display');
        if (dateString) {
            element.textContent = formatDateForDisplay(dateString);
        }
    });
}

function updateMatchesDateDisplay() {
    document.querySelectorAll('[data-match-date], [data-date-match]').forEach(function(element) {
        const dateString = element.getAttribute('data-match-date') || element.getAttribute('data-date-match');
        if (dateString) {
            element.textContent = formatDateForDisplay(dateString);
        }
    });
}

function initializeDateDisplays() {
    updateProfileDateDisplay();
    updateMatchesDateDisplay();
}

function initDateFormatting() {
    initializeDateDisplays();
    
    document.addEventListener('DOMContentLoaded', initializeDateDisplays);
}

window.formatDateForDisplay = formatDateForDisplay;
window.formatDateForInput = formatDateForInput;
window.updateProfileDateDisplay = updateProfileDateDisplay;
window.updateMatchesDateDisplay = updateMatchesDateDisplay;
window.initDateFormatting = initDateFormatting;

initDateFormatting();
