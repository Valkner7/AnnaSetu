<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold" style="color: var(--primary-green);">
                <i class="fa-solid fa-brain"></i> AI Crop Planning (फसल योजना)
            </h2>
            <p class="text-muted">Get AI-assisted recommendations based on your land and soil type.</p>
        </div>
    </div>

    <div class="row">
        <!-- Input Form -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100" style="border-top: 3px solid var(--primary-orange);">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Farm Details (खेत का विवरण)</h5>
                    <form id="aiPlanForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Land (भूमि चुनें)</label>
                            <select class="form-select" id="land_id" required>
                                <option value="">-- Choose Land --</option>
                                <?php foreach($lands as $land): ?>
                                    <option value="<?= $land['id'] ?>" data-soil="<?= $land['soil_type'] ?>">Land #<?= $land['id'] ?> - <?= $land['area'] ?> Acres (<?= $land['soil_type'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Budget (बजट ₹)</label>
                            <input type="number" class="form-control" id="budget" placeholder="e.g. 50000" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Planned Sowing Date (बुवाई की तारीख)</label>
                            <input type="date" class="form-control" id="sowing_date" required>
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold" id="btnAnalyze">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Analyze with AI (AI विश्लेषण)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- AI Output Area -->
        <div class="col-lg-8">
            <div id="aiLoading" class="text-center py-5 d-none">
                <div class="spinner-grow text-success" role="status" style="width: 3rem; height: 3rem;"></div>
                <h4 class="mt-3" style="color: var(--primary-green);">SmartHarvest AI is analyzing soil, weather, and market trends...</h4>
            </div>

            <div id="aiResults" class="d-none">
                <div class="alert alert-warning mb-4" id="aiDisclaimer"></div>
                
                <h4 class="fw-bold mb-3">Top Recommendations</h4>
                <div class="row g-3" id="recommendationCards">
                    <!-- Cards injected via JS -->
                </div>
            </div>

            <!-- Placeholder -->
            <div id="aiPlaceholder" class="text-center py-5 bg-light rounded shadow-sm border">
                <i class="fa-solid fa-robot fa-4x mb-3 text-muted"></i>
                <h4 class="text-muted">Enter your details and click Analyze</h4>
                <p class="text-muted">The AI will predict yield, revenue, and water requirements.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('aiPlanForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    document.getElementById('aiPlaceholder').classList.add('d-none');
    document.getElementById('aiResults').classList.add('d-none');
    document.getElementById('aiLoading').classList.remove('d-none');
    
    const btn = document.getElementById('btnAnalyze');
    btn.disabled = true;

    try {
        const res = await fetch('<?= BASE_URL ?>/index.php?url=farmer/getAiRecommendation', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                land_id: document.getElementById('land_id').value,
                budget: document.getElementById('budget').value,
                sowing_date: document.getElementById('sowing_date').value
            })
        });
        
        const data = await res.json();
        
        // Simulate network delay for AI "thinking" effect
        setTimeout(() => {
            document.getElementById('aiLoading').classList.add('d-none');
            
            if(data.success) {
                document.getElementById('aiResults').classList.remove('d-none');
                document.getElementById('aiDisclaimer').innerText = data.disclaimer;
                
                let cardsHtml = '';
                data.recommendations.forEach(rec => {
                    const badgeColor = rec.risk_level === 'Low' ? 'success' : (rec.risk_level === 'Medium' ? 'warning' : 'danger');
                    
                    cardsHtml += `
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid var(--primary-green);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h4 class="fw-bold text-dark m-0">${rec.crop} <small class="text-muted">(${rec.crop_hi})</small></h4>
                                    <span class="badge bg-${badgeColor}">Risk: ${rec.risk_level}</span>
                                </div>
                                <div class="progress mb-3" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: ${rec.suitability_score}%" title="Suitability: ${rec.suitability_score}%"></div>
                                </div>
                                <ul class="list-unstyled mb-3">
                                    <li><i class="fa-solid fa-calendar-days text-muted w-20px"></i> <strong>Duration:</strong> ${rec.expected_duration}</li>
                                    <li><i class="fa-solid fa-droplet text-muted w-20px"></i> <strong>Water Req:</strong> ${rec.water_requirement}</li>
                                    <li><i class="fa-solid fa-weight-hanging text-muted w-20px"></i> <strong>Est. Yield:</strong> ${rec.estimated_yield}</li>
                                    <li><i class="fa-solid fa-indian-rupee-sign text-muted w-20px"></i> <strong>Est. Revenue:</strong> <span class="text-success fw-bold">${rec.estimated_revenue}</span></li>
                                </ul>
                                <div class="p-2 bg-light rounded text-sm text-muted">
                                    <i class="fa-solid fa-lightbulb text-warning"></i> ${rec.reason}
                                </div>
                                <button class="btn btn-outline-success w-100 mt-3" onclick="window.location.href='<?= BASE_URL ?>/index.php?url=profile'">Select & Plant Crop</button>
                            </div>
                        </div>
                    </div>`;
                });
                
                document.getElementById('recommendationCards').innerHTML = cardsHtml;
            } else {
                showToast('error', "Failed to fetch AI recommendations.");
                btn.disabled = false;
                btn.innerHTML = 'Ask AI';
            }
        }, 1500);
        
    } catch(err) {
        console.error('Error:', err);
        showToast('error', 'Network Error.');
        btn.disabled = false;
        btn.innerHTML = 'Ask AI';
        document.getElementById('aiLoading').classList.add('d-none');
        document.getElementById('aiPlaceholder').classList.remove('d-none');
    }
});
</script>
<style>.w-20px{width: 25px; text-align: center;}</style>
