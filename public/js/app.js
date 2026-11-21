let selectedClass = null;
let selectedSubject = null;
let selectedExamId = null;
let currentQuestions = [];
let userAnswers = [];
let timerInterval = null;
let timeRemaining = 0;
let candidateName = "";
let candidateRoll = "";

const userBox = document.getElementById("user-box");
const userName = document.getElementById("user-name");
const logoutBtn = document.getElementById("logout-btn");
const bootStatus = document.getElementById("boot-status");
const bootRetry = document.getElementById("boot-retry");
const subjectSection = document.getElementById("subject-section");
const subjectStatus = document.getElementById("subject-status");
const subjectRetry = document.getElementById("subject-retry");
const subjectGrid = document.getElementById("subject-grid");
const historyStatus = document.getElementById("history-status");
const historyRetry = document.getElementById("history-retry");
const historyList = document.getElementById("history-list");

function handleApiError(error) {
  if (error instanceof ApiError && error.status === 401) {
    window.location.replace("login.html");
    return true;
  }
  return false;
}

// Validate Candidate Name & 6-Digit FBISE Roll Number
function validateCandidateForm() {
  const nameInput = document.getElementById("student-name");
  const rollInput = document.getElementById("roll-number");
  const nameError = document.getElementById("name-error");
  const rollError = document.getElementById("roll-error");

  if (!nameInput || !rollInput) return true;

  let isValid = true;
  if (nameError) nameError.textContent = "";
  if (rollError) rollError.textContent = "";
  nameInput.classList.remove("input-invalid");
  rollInput.classList.remove("input-invalid");

  const trimmedName = nameInput.value.trim();
  const trimmedRoll = rollInput.value.trim();

  if (trimmedName.length < 3) {
    if (nameError) nameError.textContent = "Please enter your full name (at least 3 characters) before submitting.";
    nameInput.classList.add("input-invalid");
    isValid = false;
  }

  if (!/^[0-9]{6}$/.test(trimmedRoll)) {
    if (rollError) rollError.textContent = "Please enter a valid 6-digit FBISE Roll Number before submitting.";
    rollInput.classList.add("input-invalid");
    isValid = false;
  }

  if (isValid) {
    candidateName = trimmedName;
    candidateRoll = trimmedRoll;
  }

  return isValid;
}

// 1. Select Class & Display FBISE Subjects Below
async function selectClass(className) {
  selectedClass = className;

  // Save non-sensitive UI preference
  try {
    localStorage.setItem("fbise_last_class", className);
  } catch (e) {
    // Ignore storage errors in restricted/private browser modes
  }

  document.querySelectorAll(".class-btn").forEach((btn) => {
    btn.classList.toggle("selected", btn.textContent.trim() === className);
  });

  // 1. LOADING STATE
  subjectSection.style.display = "block";
  subjectGrid.innerHTML = "";
  subjectStatus.textContent = "Loading subjects…";
  subjectRetry.hidden = true;

  try {
    const query = encodeURIComponent(className);
    const data = await apiRequest(`exams.php?class=${query}`);

    // Ignore stale response if the user clicked another class while loading
    if (selectedClass !== className) return;

    // 2. EMPTY STATE
    if (!data.exams || data.exams.length === 0) {
      subjectStatus.textContent = `No subjects found for ${className}.`;
      return;
    }

    // 3. SUCCESS STATE
    subjectStatus.textContent = "";
    data.exams.forEach((exam) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "subject-btn";
      const mins = Math.round(exam.duration_seconds / 60);
      btn.textContent = `${exam.subject} (${exam.question_count} MCQs • ${mins} min)`;
      btn.onclick = () => startQuiz(exam.subject, exam.id);
      subjectGrid.appendChild(btn);
    });
  } catch (error) {
    if (handleApiError(error)) return;
    if (selectedClass !== className) return;

    // 4. ERROR STATE
    subjectStatus.textContent = error.message;
    subjectRetry.hidden = false;
  }
}

subjectRetry.addEventListener("click", () => {
  if (selectedClass) {
    selectClass(selectedClass);
  }
});

// 2. Start Board Exam Immediately & Render OMR Table
async function startQuiz(subjectName, examId) {
  selectedSubject = subjectName;
  selectedExamId = examId;

  // 1. LOADING STATE in subjectStatus while fetching paper
  subjectStatus.textContent = `Loading ${subjectName} paper…`;
  subjectRetry.hidden = true;

  try {
    const data = await apiRequest(`questions.php?exam_id=${encodeURIComponent(examId)}`);

    if (!data.questions || data.questions.length === 0) {
      subjectStatus.textContent = "This exam currently has no questions.";
      return;
    }

    subjectStatus.textContent = "";
    currentQuestions = data.questions;
    timeRemaining = data.exam.duration_seconds;
    userAnswers = new Array(currentQuestions.length).fill(null);

    document.getElementById("selection-screen").style.display = "none";
    document.getElementById("quiz-screen").style.display = "block";
    document.getElementById("quiz-title").textContent = `${data.exam.class_name} - ${data.exam.subject}`;
    window.scrollTo(0, 0);

    renderFBISETable();
    startTimer();
  } catch (error) {
    if (handleApiError(error)) return;
    subjectStatus.textContent = error.message;
  }
}

// 3. Render Table Matching FBISE Sheet Layout
function renderFBISETable() {
  const omrBody = document.getElementById("omr-body");
  omrBody.innerHTML = "";

  currentQuestions.forEach((q, qIdx) => {
    const tr = document.createElement("tr");
    tr.id = `row-${qIdx}`;

    tr.innerHTML = `
      <td class="sno-cell">${qIdx + 1}.</td>
      <td class="question-cell">${q.question}</td>
      <td>${q.options[0]}</td>
      <td>${q.options[1]}</td>
      <td>${q.options[2]}</td>
      <td>${q.options[3]}</td>
      <td class="bubble-cell"><button type="button" class="board-bubble" aria-label="Question ${qIdx + 1} Option A" onclick="fillBubble(${qIdx}, 0)"></button></td>
      <td class="bubble-cell"><button type="button" class="board-bubble" aria-label="Question ${qIdx + 1} Option B" onclick="fillBubble(${qIdx}, 1)"></button></td>
      <td class="bubble-cell"><button type="button" class="board-bubble" aria-label="Question ${qIdx + 1} Option C" onclick="fillBubble(${qIdx}, 2)"></button></td>
      <td class="bubble-cell"><button type="button" class="board-bubble" aria-label="Question ${qIdx + 1} Option D" onclick="fillBubble(${qIdx}, 3)"></button></td>
    `;

    omrBody.appendChild(tr);
  });
}

// 4. Fill Bubble & Lock Answer for that Question
function fillBubble(qIdx, optIdx) {
  if (userAnswers[qIdx] !== null) return;

  userAnswers[qIdx] = optIdx;

  const row = document.getElementById(`row-${qIdx}`);
  const bubbles = row.querySelectorAll(".board-bubble");

  bubbles.forEach((btn, idx) => {
    btn.disabled = true;
    if (idx === optIdx) {
      btn.classList.add("filled");
    }
  });
}

// 5. Section-A Board Timer (Auto-submits even if Name/Roll No are empty)
function startTimer() {
  updateTimerDisplay();
  timerInterval = setInterval(() => {
    timeRemaining--;
    updateTimerDisplay();

    if (timeRemaining <= 0) {
      clearInterval(timerInterval);
      const timeOutMsg = document.getElementById("time-out-msg");
      if (timeOutMsg) timeOutMsg.style.display = "block";
      submitQuiz(true);
    }
  }, 1000);
}

function updateTimerDisplay() {
  const mins = Math.floor(timeRemaining / 60);
  const secs = timeRemaining % 60;
  document.getElementById("time-display").textContent =
    `${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;
}

// 6. Submit to Server, Grade & Display Detailed Review
async function submitQuiz(isAutoSubmit = false) {
  if (!isAutoSubmit) {
    if (!validateCandidateForm()) {
      const formEl = document.getElementById("candidate-form");
      if (formEl) formEl.scrollIntoView({ behavior: "smooth" });
      return;
    }
  } else {
    const nameInput = document.getElementById("student-name");
    const rollInput = document.getElementById("roll-number");
    const nameVal = nameInput ? nameInput.value.trim() : "";
    const rollVal = rollInput ? rollInput.value.trim() : "";
    candidateName = nameVal.length >= 3 ? nameVal : "Not Provided";
    candidateRoll = /^[0-9]{6}$/.test(rollVal) ? rollVal : "Not Provided";
  }

  const submitBtn = document.getElementById("submit-btn");
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Submitting…";
  }

  // Build payload matching each question's database ID
  const answersPayload = currentQuestions.map((q, idx) => ({
    question_id: q.id,
    chosen_option: userAnswers[idx]
  }));

  try {
    const data = await apiRequest("submit.php", {
      method: "POST",
      body: {
        exam_id: selectedExamId,
        auto_submitted: isAutoSubmit,
        answers: answersPayload
      }
    });

    clearInterval(timerInterval);
    timerInterval = null;

    const reviewBox = document.getElementById("answers-review");
    reviewBox.innerHTML = "";

    data.review.forEach((item, idx) => {
      const div = document.createElement("div");
      div.className = "review-item";
      const userText = item.chosen_option !== null
        ? item.options[item.chosen_option]
        : "<em>Blank (Unfilled)</em>";

      div.innerHTML = `
        <p><strong>Q${idx + 1}: ${item.question}</strong></p>
        <p>Your Answer: ${userText} ${item.is_correct ? "✅" : "❌"}</p>
        <p style="color: #16a34a; font-weight: bold;">Correct Answer: ${item.options[item.correct_option]}</p>
        ${item.explanation ? `<p style="font-size: 11px; color: #475569; margin-top: 3px;"><em>Explanation: ${item.explanation}</em></p>` : ""}
      `;
      reviewBox.appendChild(div);
    });

    const timeoutBanner = document.getElementById("result-timeout-banner");
    if (timeoutBanner) {
      timeoutBanner.style.display = isAutoSubmit ? "block" : "none";
    }

    const resultInfo = document.getElementById("result-candidate-info");
    if (resultInfo) {
      resultInfo.textContent =
        `Candidate: ${candidateName} | Roll No: ${candidateRoll} | Exam: ${selectedClass} - ${selectedSubject}`;
    }

    document.getElementById("final-score").textContent = data.score;
    document.getElementById("total-score").textContent = data.total;

    document.getElementById("quiz-screen").style.display = "none";
    document.getElementById("result-screen").style.display = "block";
    window.scrollTo(0, 0);
  } catch (error) {
    if (handleApiError(error)) return;
    alert(error.message || "Could not submit your exam. Please try again.");
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = "Submit Answer Sheet";
    }
  }
}

// 7. Past Attempts History (Read, Update Note & Delete)
async function loadAttempts() {
  if (!historyStatus || !historyList) return;

  historyStatus.textContent = "Loading past attempts…";
  if (historyRetry) historyRetry.hidden = true;

  try {
    const data = await apiRequest("attempts.php");

    // Clear right before rendering to prevent duplicate entries
    historyList.innerHTML = "";

    if (!data.attempts || data.attempts.length === 0) {
      historyStatus.textContent = "No attempts recorded yet. Complete an exam above to see your history.";
      return;
    }

    historyStatus.textContent = "";
    data.attempts.forEach((att) => {
      const card = document.createElement("div");
      card.className = "review-item";
      card.style.marginBottom = "10px";

      const title = document.createElement("p");
      const strong = document.createElement("strong");
      const dateStr = new Date(att.submitted_at * 1000).toLocaleString();
      strong.textContent = `${att.class_name} - ${att.subject} | Score: ${att.score}/${att.total} (${dateStr})`;
      title.appendChild(strong);

      const noteEl = document.createElement("p");
      noteEl.style.fontSize = "13px";
      noteEl.style.color = "#334155";
      noteEl.textContent = att.note ? `Note: ${att.note}` : "Note: (None)";

      const actions = document.createElement("div");
      actions.style.marginTop = "6px";
      actions.style.display = "flex";
      actions.style.gap = "8px";
      actions.style.flexWrap = "wrap";

      const editBtn = document.createElement("button");
      editBtn.type = "button";
      editBtn.textContent = "Edit Note";
      editBtn.onclick = () => openNoteEditor(card, att, noteEl, actions);

      const delBtn = document.createElement("button");
      delBtn.type = "button";
      delBtn.textContent = "Delete";
      delBtn.onclick = () => deleteAttempt(att.id, delBtn);

      actions.appendChild(editBtn);
      actions.appendChild(delBtn);
      card.appendChild(title);
      card.appendChild(noteEl);
      card.appendChild(actions);
      historyList.appendChild(card);
    });
  } catch (error) {
    if (handleApiError(error)) return;
    historyStatus.textContent = error.message;
    if (historyRetry) historyRetry.hidden = false;
  }
}

function openNoteEditor(card, att, noteEl, actions) {
  noteEl.hidden = true;
  actions.hidden = true;

  const editor = document.createElement("div");
  editor.style.marginTop = "6px";
  editor.style.display = "flex";
  editor.style.gap = "8px";
  editor.style.flexWrap = "wrap";

  const input = document.createElement("input");
  input.type = "text";
  input.maxLength = 250;
  input.placeholder = "Add a study note (max 250 chars)…";
  input.value = att.note || "";
  input.style.flex = "1";
  input.style.minWidth = "200px";

  const saveBtn = document.createElement("button");
  saveBtn.type = "button";
  saveBtn.textContent = "Save";

  const cancelBtn = document.createElement("button");
  cancelBtn.type = "button";
  cancelBtn.textContent = "Cancel";

  cancelBtn.onclick = () => {
    editor.remove();
    noteEl.hidden = false;
    actions.hidden = false;
  };

  saveBtn.onclick = async () => {
    saveBtn.disabled = true;
    cancelBtn.disabled = true;
    saveBtn.textContent = "Saving…";

    try {
      await apiRequest("attempt-update.php", {
        method: "POST",
        body: {
          attempt_id: att.id,
          note: input.value
        }
      });
      await loadAttempts();
    } catch (error) {
      if (handleApiError(error)) return;
      alert(error.message || "Could not update note.");
      saveBtn.disabled = false;
      cancelBtn.disabled = false;
      saveBtn.textContent = "Save";
    }
  };

  editor.appendChild(input);
  editor.appendChild(saveBtn);
  editor.appendChild(cancelBtn);
  card.appendChild(editor);
  input.focus();
}

async function deleteAttempt(attemptId, btn) {
  btn.disabled = true;
  btn.textContent = "Deleting…";

  try {
    await apiRequest("attempt-delete.php", {
      method: "POST",
      body: { attempt_id: attemptId }
    });
    await loadAttempts();
  } catch (error) {
    if (handleApiError(error)) return;
    alert(error.message || "Could not delete attempt.");
    btn.disabled = false;
    btn.textContent = "Delete";
  }
}

if (historyRetry) {
  historyRetry.addEventListener("click", loadAttempts);
}

// 8. Restart App Completely
function restartApp() {
  if (timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
  }

  selectedClass = null;
  selectedSubject = null;
  selectedExamId = null;
  currentQuestions = [];
  userAnswers = [];
  timeRemaining = 0;
  candidateName = "";
  candidateRoll = "";

  const nameInput = document.getElementById("student-name");
  const rollInput = document.getElementById("roll-number");
  const nameError = document.getElementById("name-error");
  const rollError = document.getElementById("roll-error");
  if (nameError) nameError.textContent = "";
  if (rollError) rollError.textContent = "";
  if (nameInput) {
    nameInput.value = "";
    nameInput.classList.remove("input-invalid");
  }
  if (rollInput) {
    rollInput.value = "";
    rollInput.classList.remove("input-invalid");
  }

  const resultScreen = document.getElementById("result-screen");
  const quizScreen = document.getElementById("quiz-screen");
  const selectionScreen = document.getElementById("selection-screen");
  const timeOutMsg = document.getElementById("time-out-msg");
  const timeoutBanner = document.getElementById("result-timeout-banner");

  if (resultScreen) resultScreen.style.display = "none";
  if (quizScreen) quizScreen.style.display = "none";
  if (subjectSection) subjectSection.style.display = "none";
  if (timeOutMsg) timeOutMsg.style.display = "none";
  if (timeoutBanner) timeoutBanner.style.display = "none";
  if (selectionScreen) selectionScreen.style.display = "block";

  const omrBody = document.getElementById("omr-body");
  if (omrBody) omrBody.innerHTML = "";

  const reviewBox = document.getElementById("answers-review");
  if (reviewBox) reviewBox.innerHTML = "";

  document.querySelectorAll(".class-btn").forEach((btn) => {
    btn.classList.remove("selected");
  });

  loadAttempts();
  window.scrollTo(0, 0);
}

document.addEventListener("DOMContentLoaded", () => {
  const candidateForm = document.getElementById("candidate-form");
  if (candidateForm) {
    candidateForm.addEventListener("submit", (e) => {
      e.preventDefault();
      submitQuiz(false);
    });
  }

  const restartBtn = document.getElementById("restart-btn");
  if (restartBtn) {
    restartBtn.addEventListener("click", restartApp);
  }
});

// 9. Session Guard & Auth UI
function showApp(user) {
  userName.textContent = user.full_name;
  userBox.hidden = false;
  document.getElementById("selection-screen").style.display = "block";
  loadAttempts();

  // Restore non-sensitive UI preference (last selected class)
  try {
    const savedClass = localStorage.getItem("fbise_last_class");
    const validClasses = ["Class 9", "Class 10", "Class 11", "Class 12"];
    if (savedClass && validClasses.includes(savedClass)) {
      selectClass(savedClass);
    }
  } catch (e) {
    // Ignore storage errors
  }
}

async function boot() {
  bootStatus.textContent = "Loading…";
  bootRetry.hidden = true;

  try {
    const data = await apiRequest("me.php");

    if (!data.user) {
      window.location.replace("login.html");
      return;
    }

    showApp(data.user);
    bootStatus.textContent = "";
  } catch (error) {
    bootStatus.textContent = error.message;
    bootRetry.hidden = false;
  }
}

logoutBtn.addEventListener("click", async () => {
  logoutBtn.disabled = true;
  logoutBtn.textContent = "Logging out…";

  try {
    await apiRequest("logout.php", { method: "POST" });
    window.location.replace("login.html");
  } catch (error) {
    bootStatus.textContent = error.message;
    logoutBtn.disabled = false;
    logoutBtn.textContent = "Log out";
  }
});

bootRetry.addEventListener("click", boot);
boot();