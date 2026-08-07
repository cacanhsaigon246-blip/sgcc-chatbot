const fs = require('fs');
const logs = JSON.parse(fs.readFileSync('temp_logs.json', 'utf8'));
const last_logs = logs.slice(-15);
last_logs.forEach(l => {
    if (l.question.includes('thuc an ca canh') || l.question.includes('tep')) {
        console.log("Q: " + l.question);
        console.log("A: " + l.answer);
        console.log("-----------------------");
    }
});
