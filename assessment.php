<?php
require_once 'includes/bootstrap.php';
$pageTitle = 'Skill Assessment - CyberSphere';
$currentPage = 'assessment';
?>
<?php include 'includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-blue-900">Cybersecurity Assessment</h1>
                    <p class="text-gray-600">Test your knowledge in cybersecurity concepts</p>
                </div>
                <div class="text-center">
                    <div id="timer" class="text-2xl font-bold text-red-700">30:00</div>
                    <p class="text-sm text-gray-500">Time Remaining</p>
                </div>
            </div>

            <div class="mb-6">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Progress</span>
                    <span id="progressText">0/5</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div id="progressBar" class="bg-blue-900 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>

            <div id="questionContainer" class="space-y-6">
                <!-- Question 1: Multiple Choice -->
                <div class="question" data-question="1">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">1. What is the primary goal of penetration testing?</h3>
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition">
                            <input type="radio" name="q1" value="a" class="mt-1">
                            <span class="text-gray-700">To find and fix vulnerabilities before attackers do</span>
                        </label>
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition">
                            <input type="radio" name="q1" value="b" class="mt-1">
                            <span class="text-gray-700">To launch attacks on systems for fun</span>
                        </label>
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition">
                            <input type="radio" name="q1" value="c" class="mt-1">
                            <span class="text-gray-700">To bypass security without permission</span>
                        </label>
                    </div>
                </div>

                <!-- Question 2: Multiple Choice -->
                <div class="question" data-question="2">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">2. Which protocol is used for secure web communication?</h3>
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition">
                            <input type="radio" name="q2" value="a" class="mt-1">
                            <span class="text-gray-700">HTTP</span>
                        </label>
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition">
                            <input type="radio" name="q2" value="b" class="mt-1">
                            <span class="text-gray-700">HTTPS</span>
                        </label>
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition">
                            <input type="radio" name="q2" value="c" class="mt-1">
                            <span class="text-gray-700">FTP</span>
                        </label>
                    </div>
                </div>

                <!-- Question 3: Coding Challenge -->
                <div class="question" data-question="3">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">3. Coding Challenge: Validate an IP Address</h3>
                    <p class="text-gray-600 mb-3">Write a function that checks if a given string is a valid IPv4 address.</p>
                    <textarea id="codeAnswer" rows="8" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm" placeholder="Write your code here..."></textarea>
                </div>

                <!-- Question 4: File Upload -->
                <div class="question" data-question="4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">4. Upload Packet Tracer File (.pkt)</h3>
                    <p class="text-gray-600 mb-3">Upload your network topology or packet capture file.</p>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center">
                        <input type="file" id="fileUpload" accept=".pkt,.pcap,.cap" class="hidden">
                        <label for="fileUpload" class="cursor-pointer">
                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="text-gray-600 font-medium">Click to upload or drag and drop</p>
                            <p class="text-gray-400 text-sm mt-1">.pkt, .pcap, .cap files only</p>
                        </label>
                        <p id="fileName" class="text-sm text-blue-700 font-medium mt-3"></p>
                    </div>
                </div>

                <!-- Question 5: Short Answer -->
                <div class="question" data-question="5">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">5. What is the CIA Triad?</h3>
                    <textarea id="shortAnswer" rows="4" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Explain the CIA Triad..."></textarea>
                </div>
            </div>

            <div class="mt-8 flex gap-4">
                <button id="submitBtn" class="flex-1 bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 rounded-xl transition">
                    Submit Assessment
                </button>
            </div>
        </div>
    </div>
</div>

<div id="confirmModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-3">Submit Assessment?</h3>
            <p class="text-gray-600">Are you sure you want to submit your assessment? You can't make changes after submission.</p>
        </div>
        <div class="flex border-t border-gray-200">
            <button id="cancelSubmit" class="flex-1 py-3 font-semibold text-gray-600 hover:bg-gray-50 transition">Cancel</button>
            <button id="confirmSubmit" class="flex-1 py-3 font-semibold bg-blue-900 text-white hover:bg-blue-800 transition">Submit</button>
        </div>
    </div>
</div>

<div id="successModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
        <div class="p-8 text-center">
            <div class="w-16 h-16 mx-auto bg-green-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Assessment Submitted!</h3>
            <p class="text-gray-600 mb-6">Your assessment has been successfully submitted. We'll review your answers soon.</p>
            <button onclick="window.location.href='index.php'" class="w-full bg-blue-900 text-white font-bold py-3 rounded-xl hover:bg-blue-800 transition">
                Go to Dashboard
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    // Timer
    let totalSeconds = 30 * 60;
    const timerEl = document.getElementById('timer');
    
    function updateTimer() {
        const minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
        const seconds = (totalSeconds % 60).toString().padStart(2, '0');
        timerEl.textContent = `${minutes}:${seconds}`;
        
        if (totalSeconds <= 0) {
            submitAssessment();
        } else {
            totalSeconds--;
        }
    }
    
    let timerInterval = setInterval(updateTimer, 1000);
    
    // Progress tracking
    const questions = document.querySelectorAll('.question');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    
    function updateProgress() {
        let answered = 0;
        questions.forEach(q => {
            const inputs = q.querySelectorAll('input[type="radio"], textarea');
            let hasAnswer = false;
            inputs.forEach(input => {
                if (input.type === 'radio' && input.checked) hasAnswer = true;
                if (input.type !== 'radio' && input.value.trim()) hasAnswer = true;
            });
            if (hasAnswer) answered++;
        });
        const percent = (answered / questions.length) * 100;
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `${answered}/${questions.length}`;
    }
    
    document.querySelectorAll('input[type="radio"], textarea').forEach(el => {
        el.addEventListener('change', updateProgress);
        el.addEventListener('input', updateProgress);
    });
    
    // File upload
    const fileInput = document.getElementById('fileUpload');
    const fileNameEl = document.getElementById('fileName');
    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) {
            fileNameEl.textContent = fileInput.files[0].name;
        }
        updateProgress();
    });
    
    // Submit flow
    const submitBtn = document.getElementById('submitBtn');
    const confirmModal = document.getElementById('confirmModal');
    const successModal = document.getElementById('successModal');
    const cancelSubmit = document.getElementById('cancelSubmit');
    const confirmSubmitBtn = document.getElementById('confirmSubmit');
    
    submitBtn.addEventListener('click', () => {
        confirmModal.classList.remove('hidden');
        confirmModal.classList.add('flex');
    });
    
    cancelSubmit.addEventListener('click', () => {
        confirmModal.classList.add('hidden');
        confirmModal.classList.remove('flex');
    });
    
    function submitAssessment() {
        clearInterval(timerInterval);
        confirmModal.classList.add('hidden');
        confirmModal.classList.remove('flex');
        successModal.classList.remove('hidden');
        successModal.classList.add('flex');
    }
    
    confirmSubmitBtn.addEventListener('click', submitAssessment);
})();
</script>

<?php include 'includes/footer.php'; ?>
