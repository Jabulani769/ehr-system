document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('vitalsChart')) {
        // Extract data for Chart.js
        const labels = vitalsData.map(data => new Date(data.recorded_at).toLocaleString());
        const systolic = vitalsData.map(data => parseInt(data.blood_pressure.split('/')[0]));
        const diastolic = vitalsData.map(data => parseInt(data.blood_pressure.split('/')[1]));
        const heartRate = vitalsData.map(data => parseInt(data.heart_rate));
        const temperature = vitalsData.map(data => parseFloat(data.temperature));
        const respiratoryRate = vitalsData.map(data => parseInt(data.respiratory_rate));

        // Debug data
        console.log('Vitals Data:', vitalsData);
        console.log('Labels:', labels);
        console.log('Systolic:', systolic);
        console.log('Diastolic:', diastolic);
        console.log('Heart Rate:', heartRate);
        console.log('Temperature:', temperature);
        console.log('Respiratory Rate:', respiratoryRate);

        const ctx = document.getElementById('vitalsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Systolic BP (mmHg)',
                        data: systolic,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)', // For point visibility
                        fill: false,
                        tension: 0.4, // Smooth, wavy lines
                        pointRadius: 4 // Visible points
                    },
                    {
                        label: 'Diastolic BP (mmHg)',
                        data: diastolic,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        fill: false,
                        tension: 0.4,
                        pointRadius: 4
                    },
                    {
                        label: 'Heart Rate (bpm)',
                        data: heartRate,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        fill: false,
                        tension: 0.4,
                        pointRadius: 4
                    },
                    {
                        label: 'Temperature (°C)',
                        data: temperature,
                        borderColor: 'rgba(255, 206, 86, 1)',
                        backgroundColor: 'rgba(255, 206, 86, 0.2)',
                        fill: false,
                        tension: 0.4,
                        pointRadius: 4
                    },
                    {
                        label: 'Respiratory Rate (breaths/min)',
                        data: respiratoryRate,
                        borderColor: 'rgba(153, 102, 255, 1)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        fill: false,
                        tension: 0.4,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        title: { display: true, text: 'Date/Time' }
                    },
                    y: {
                        title: { display: true, text: 'Value' },
                        beginAtZero: false
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    }
});