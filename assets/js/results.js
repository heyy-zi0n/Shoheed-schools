document.addEventListener('DOMContentLoaded', () => {
    // Live CA + Exam total preview for result entry
    const scoreRows = document.querySelectorAll('.score-row');
    
    scoreRows.forEach(row => {
        const caInput = row.querySelector('.ca-input');
        const examInput = row.querySelector('.exam-input');
        const totalCell = row.querySelector('.total-cell');
        
        if (caInput && examInput && totalCell) {
            const calculateTotal = () => {
                const ca = parseFloat(caInput.value) || 0;
                const exam = parseFloat(examInput.value) || 0;
                // Clamp values strictly to prevent visual artifacts
                const clampedCa = Math.min(Math.max(ca, 0), 40);
                const clampedExam = Math.min(Math.max(exam, 0), 60);
                const total = clampedCa + clampedExam;
                totalCell.textContent = total;
            };
            
            caInput.addEventListener('input', calculateTotal);
            examInput.addEventListener('input', calculateTotal);
        }
    });
});
