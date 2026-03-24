// Update current time every second
        function updateCurrentTime() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('currentTime').textContent = hours + ':' + minutes + ':' + seconds;

            // Update time input
            document.getElementById('clock_time').value = hours + ':' + minutes;
        }

        // Update time every second
        setInterval(updateCurrentTime, 1000);
        updateCurrentTime();

        // Clock type button handlers
        document.querySelectorAll('.clock-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                // Remove active class from all buttons
                document.querySelectorAll('.clock-btn').forEach(b => b.classList.remove('active'));

                // Add active class to clicked button
                this.classList.add('active');

                // Set hidden input value
                document.getElementById('clock_type').value = this.getAttribute('data-type');
            });
        });

        // Form validation
        document.getElementById('biometricClockForm').addEventListener('submit', function(e) {
            const student = document.getElementById('student_id').value;
            const clockType = document.getElementById('clock_type').value;

            if (!student) {
                e.preventDefault();
                alert('Please select a student');
                return false;
            }

            if (!clockType) {
                e.preventDefault();
                alert('Please select a clock type');
                return false;
            }
        });
