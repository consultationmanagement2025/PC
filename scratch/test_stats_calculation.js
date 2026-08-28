const fs = require('fs');

// Mock data matching actual DB output
const sampleFeedback = [];
for (let i = 1; i <= 28; i++) sampleFeedback.push({ id: i, consultationId: 1, sentiment_tag: 'positive', rating: 5, category: 'Sanitation' });
for (let i = 29; i <= 40; i++) sampleFeedback.push({ id: i, consultationId: 1, sentiment_tag: 'neutral', rating: 3, category: 'Traffic' });
for (let i = 41; i <= 49; i++) sampleFeedback.push({ id: i, consultationId: 1, sentiment_tag: 'negative', rating: 1, category: 'Noise' });

const AppData = {
    consultations: Array.from({ length: 16 }, (_, i) => ({ id: i + 1, status: i < 7 ? 'active' : (i < 12 ? 'closed' : 'submitted') })),
    feedback: sampleFeedback,
    documents: Array.from({ length: 13 }, (_, i) => ({ id: i + 1, original_filename: `Doc_${i+1}.pdf` }))
};

const totalConsults = AppData.consultations.length;
const draftConsults = AppData.consultations.filter(c => ['draft', 'pending', 'submitted', 'under_review', 'pending_review'].includes(c.status)).length;
const activeConsults = AppData.consultations.filter(c => c.status === 'active').length;
const closedConsults = AppData.consultations.filter(c => c.status === 'closed').length;

const totalFeedback = AppData.feedback.length;
const avgFeedback = Math.round(totalFeedback / totalConsults);
const totalDocuments = AppData.documents.length;

console.log("=== CALCULATED STATS ===");
console.log(`Total Consultations: ${totalConsults} (${activeConsults} Active, ${closedConsults} Closed, ${draftConsults} Pending)`);
console.log(`Total Feedback: ${totalFeedback} (Avg ${avgFeedback} per consultation)`);
console.log(`Total Documents: ${totalDocuments}`);
console.log(`Feedback Breakdown: 28 Positive, 12 Neutral, 9 Negative`);
