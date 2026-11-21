//coverting data.js into json, verify hte count of questions

const fs = require("fs");
const path = require("path");

const source = path.join(__dirname, "..", "public", "js", "data.js");
const tmp    = path.join(__dirname, "_tmp.js");
const out    = path.join(__dirname, "..", "database", "quiz-data.json");

fs.writeFileSync(tmp, fs.readFileSync(source, "utf8") + "\nmodule.exports = quizData;");
const data = require(tmp);
fs.writeFileSync(out, JSON.stringify(data, null, 2));
fs.unlinkSync(tmp);

let exams = 0, questions = 0;
for (const cls of Object.keys(data)) for (const subj of Object.keys(data[c])) {
  exams++; questions += data[cls][subj].questions.length;
}
console.log("Wrote", out);
console.log("Exams:", exams, "Questions:", questions);