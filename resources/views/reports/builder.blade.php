@extends('layouts.app')

@section('title', 'Report Builder')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-file-earmark-bar-graph me-2"></i>Report Builder</h1>
        <p class="text-muted mb-0">Create custom reports with dynamic filters</p>
    </div>
</div>

<div class="row g-4">
    <!-- Left Panel: Configuration -->
    <div class="col-lg-5">
        <!-- Saved Reports -->
        <div class="card mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bookmark me-2"></i>Saved Reports</span>
            </div>
            <div class="card-body py-2">
                @if($savedReports->count() > 0)
                    <div class="list-group list-group-flush">
                        @foreach($savedReports as $report)
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                            <a href="#" class="text-decoration-none load-report" data-id="{{ $report->id }}">
                                <i class="bi bi-file-text me-1"></i>{{ $report->name }}
                            </a>
                            <button class="btn btn-sm btn-outline-danger delete-report" data-id="{{ $report->id }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0 small">No saved reports yet</p>
                @endif
            </div>
        </div>

        <!-- Column Selection -->
        <div class="card mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-layout-three-columns me-2"></i>Columns</span>
                <div>
                    <button type="button" class="btn btn-sm btn-link p-0 me-2" id="selectAllCols">All</button>
                    <button type="button" class="btn btn-sm btn-link p-0" id="selectNoneCols">None</button>
                </div>
            </div>
            <div class="card-body py-2">
                <!-- Selected Columns -->
                <div class="mb-3">
                    <label class="small text-muted fw-bold mb-1">Selected Order (Drag to rearrange)</label>
                    <div id="selectedColumnsList" class="list-group shadow-sm" style="max-height: 200px; overflow-y: auto; overflow-x: hidden; border: 1px solid #dee2e6; border-radius: 4px; min-height: 40px;">
                        <!-- Items will be added here dynamically -->
                        <div class="p-2 text-center text-muted small fst-italic" id="emptySelectionMsg">No columns selected</div>
                    </div>
                </div>

                <hr class="my-2">

                <!-- Available Columns -->
                <input type="text" class="form-control form-control-sm mb-2" id="columnSearch" placeholder="Search columns...">
                
                <div style="max-height: 300px; overflow-y: auto;">
                    @foreach($groupedColumns as $group => $cols)
                    <div class="mb-2 column-group">
                        <small class="text-muted fw-bold">{{ $group }}</small>
                        <div class="row g-1">
                            @foreach($cols as $key => $col)
                            <div class="col-6 column-item" data-label="{{ strtolower($col['label']) }}">
                                <div class="form-check">
                                    <input class="form-check-input column-checkbox" type="checkbox" value="{{ $key }}" id="col_{{ str_replace('.', '_', $key) }}" 
                                        data-label="{{ $col['label'] }}"
                                        {{ in_array($key, ['job_number', 'customer_name', 'service_advisor', 'status', 'total_sales']) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="col_{{ str_replace('.', '_', $key) }}">{{ $col['label'] }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Query Builder -->
        <div class="card mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-funnel me-2"></i>Filter Logic</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" id="resetRules">Reset</button>
            </div>
            <div class="card-body p-3 bg-light">
                <div id="queryBuilder"></div>
            </div>
        </div>

        <!-- PDF/Print Settings -->
        <div class="card mb-3">
            <div class="card-header py-2" data-bs-toggle="collapse" data-bs-target="#pdfSettings" role="button">
                <i class="bi bi-file-pdf me-2"></i>PDF/Print Settings
                <i class="bi bi-chevron-down float-end"></i>
            </div>
            <div class="collapse" id="pdfSettings">
                <div class="card-body py-2">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small mb-0">Report Title</label>
                            <input type="text" class="form-control form-control-sm" id="reportTitle" placeholder="Job Report" value="Job Report">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-0">Title Align</label>
                            <select class="form-select form-select-sm" id="titleAlign">
                                <option value="center">Center</option>
                                <option value="left">Left</option>
                                <option value="right">Right</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-0">Orientation</label>
                            <select class="form-select form-select-sm" id="pageOrientation">
                                <option value="portrait">Portrait</option>
                                <option value="landscape">Landscape</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-0">Header Text</label>
                            <input type="text" class="form-control form-control-sm" id="headerText" placeholder="e.g. {title} - Generated {date}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-0">Footer Text</label>
                            <input type="text" class="form-control form-control-sm" id="footerText" placeholder="e.g. Page {page} of {pages}" value="Page {page} of {pages}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-body py-3">
                <button type="button" class="btn btn-primary w-100 mb-2" id="previewBtn">
                    <i class="bi bi-eye me-1"></i>Preview
                </button>
                <div class="btn-group w-100 mb-2">
                    <button type="button" class="btn btn-success" id="exportExcel">
                        <i class="bi bi-file-earmark-excel me-1"></i>Excel
                    </button>
                    <button type="button" class="btn btn-success" id="exportCsv">
                        <i class="bi bi-filetype-csv me-1"></i>CSV
                    </button>
                    <button type="button" class="btn btn-danger" id="exportPdf">
                        <i class="bi bi-file-pdf me-1"></i>PDF
                    </button>
                    <button type="button" class="btn btn-secondary" id="exportPrint">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                </div>
                <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#saveModal">
                    <i class="bi bi-save me-1"></i>Save Report Config
                </button>
            </div>
        </div>
    </div>

    <!-- Right Panel: Preview -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2"></i>Preview</span>
                <span class="badge bg-secondary" id="totalCount">0 records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 80vh; overflow: auto;">
                    <table class="table table-hover table-sm table-bordered mb-0" id="previewTable">
                        <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
                            <tr id="previewHeader"></tr>
                        </thead>
                        <tbody id="previewBody">
                            <tr>
                                <td class="text-center text-muted py-5">
                                    <i class="bi bi-arrow-left fs-1 opacity-25"></i>
                                    <p class="mb-0 mt-2">Select columns and click Preview</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Save Modal -->
<div class="modal fade" id="saveModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-save me-2"></i>Save Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Report Name</label>
                <input type="text" class="form-control" id="reportName" placeholder="My Custom Report">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveReportBtn">Save</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .sortable-ghost { opacity: 0.4; }
    .sortable-drag { cursor: grabbing; }
    .selected-column-item { cursor: grab; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '{{ csrf_token() }}';
    
    // --- QUERY BUILDER LOGIC ---
    const columns = @json($columns);
    const filterOptions = @json($filterOptions);
    
    // Initial Rule Structure
    let queryRules = {
        condition: 'AND',
        rules: [
            { field: 'franchise', operator: 'equal', value: 'PC', type: 'string' }
        ]
    };

    function renderBuilder() {
        const container = document.getElementById('queryBuilder');
        container.innerHTML = '';
        container.appendChild(createGroup(queryRules));
    }

    function createGroup(groupData, parentRules = null, index = null) {
        const groupEl = document.createElement('div');
        groupEl.className = 'card mb-2 border-primary';
        groupEl.style.borderLeftWidth = '4px';

        const header = document.createElement('div');
        header.className = 'card-header py-1 px-2 d-flex justify-content-between align-items-center bg-white';
        
        // Group Logic Label (Badge)
        const logicBadge = document.createElement('span');
        logicBadge.className = `badge bg-${groupData.condition === 'AND' ? 'primary' : 'warning'} me-2`;
        logicBadge.textContent = groupData.condition;
        header.appendChild(logicBadge); // Just a visual indicator

        // Group Actions
        const actions = document.createElement('div');
        
        const addRuleBtn = document.createElement('button');
        addRuleBtn.className = 'btn btn-sm btn-link text-success p-1 ms-2';
        addRuleBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Rule';
        addRuleBtn.onclick = () => {
            groupData.rules.push({ field: 'job_number', operator: 'contains', value: '', type: 'string' });
            renderBuilder();
        };

        const addGroupBtn = document.createElement('button');
        addGroupBtn.className = 'btn btn-sm btn-link text-primary p-1 ms-2';
        addGroupBtn.innerHTML = '<i class="bi bi-layers"></i> Group';
        addGroupBtn.onclick = () => {
            groupData.rules.push({ condition: 'AND', rules: [] });
            renderBuilder();
        };

        const removeGroupBtn = document.createElement('button');
        removeGroupBtn.className = 'btn btn-sm btn-link text-danger p-1 ms-2';
        removeGroupBtn.innerHTML = '<i class="bi bi-trash"></i>';
        
        if (parentRules) {
            removeGroupBtn.onclick = () => {
                parentRules.splice(index, 1);
                renderBuilder();
            };
        } else {
            removeGroupBtn.disabled = true; // Cannot delete root
        }

        actions.appendChild(addRuleBtn);
        actions.appendChild(addGroupBtn);
        actions.appendChild(removeGroupBtn);
        header.appendChild(actions);

        groupEl.appendChild(header);

        // Rules Container
        const body = document.createElement('div');
        body.className = 'card-body p-2';
        
        if (groupData.rules.length === 0) {
            body.innerHTML = '<div class="text-muted small fst-italic text-center py-2">No rules. Add one above.</div>';
        }

        groupData.rules.forEach((rule, idx) => {
            // Add Divider if not first element
            if (idx > 0) {
                body.appendChild(createOperatorDivider(groupData));
            }

            if (rule.condition) {
                // Recursive Group
                body.appendChild(createGroup(rule, groupData.rules, idx));
            } else {
                // Single Rule
                body.appendChild(createRule(rule, groupData.rules, idx));
            }
        });

        groupEl.appendChild(body);
        return groupEl;
    }

    function createOperatorDivider(groupData) {
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-center my-1 position-relative';
        
        // Line
        const line = document.createElement('div');
        line.style.position = 'absolute';
        line.style.top = '50%';
        line.style.left = '0';
        line.style.right = '0';
        line.style.height = '1px';
        line.style.backgroundColor = '#dee2e6';
        line.style.zIndex = '0';
        div.appendChild(line);
        
        // Badge Button
        const badge = document.createElement('button');
        badge.type = 'button';
        badge.className = `btn btn-sm badge rounded-pill position-relative z-1 ${groupData.condition === 'AND' ? 'btn-primary' : 'btn-warning text-dark'}`;
        badge.style.minWidth = '50px';
        badge.textContent = groupData.condition;
        
        badge.onclick = () => {
            // Toggle Logic
            groupData.condition = groupData.condition === 'AND' ? 'OR' : 'AND';
            renderBuilder();
        };
        
        div.appendChild(badge);
        return div;
    }

    function createRule(ruleData, parentRules, index) {
        const row = document.createElement('div');
        row.className = 'd-flex gap-2 mb-2 align-items-center bg-white p-2 rounded shadow-sm';
        
        // Field Select
        const fieldSelect = document.createElement('select');
        fieldSelect.className = 'form-select form-select-sm';
        fieldSelect.style.width = '140px';
        
        Object.entries(columns).forEach(([key, col]) => {
            const opt = document.createElement('option');
            opt.value = key;
            opt.textContent = col.label;
            opt.selected = ruleData.field === key;
            fieldSelect.appendChild(opt);
        });

        fieldSelect.onchange = (e) => {
            ruleData.field = e.target.value;
            ruleData.type = columns[e.target.value].type;
            // Reset operator/value based on new type?
            renderBuilder();
        };

        // Operator Select
        const opSelect = document.createElement('select');
        opSelect.className = 'form-select form-select-sm';
        opSelect.style.width = '110px';
        
        const operators = getOperators(ruleData.type);
        operators.forEach(op => {
            const opt = document.createElement('option');
            opt.value = op.val;
            opt.textContent = op.label;
            opt.selected = ruleData.operator === op.val;
            opSelect.appendChild(opt);
        });
        
        opSelect.onchange = (e) => {
            ruleData.operator = e.target.value;
            // Re-render if operator changes input type (e.g. is_empty)
            if (e.target.value === 'is_empty' || e.target.value === 'is_not_empty') {
                renderBuilder();
            }
        };

        // Value Input
        let valueInput;
        
        if (ruleData.operator === 'is_empty' || ruleData.operator === 'is_not_empty') {
            valueInput = document.createElement('input');
            valueInput.type = 'hidden'; // Placeholder
        } else if (filterOptions[ruleData.field]) {
            // Select from options
            valueInput = document.createElement('select');
            valueInput.className = 'form-select form-select-sm';
            
            // Add default/empty option
            const defOpt = document.createElement('option');
            defOpt.value = '';
            defOpt.textContent = '- Select -';
            valueInput.appendChild(defOpt);

            filterOptions[ruleData.field].forEach(opt => {
                const o = document.createElement('option');
                o.value = opt;
                o.textContent = opt;
                o.selected = ruleData.value == opt; // loose check
                valueInput.appendChild(o);
            });
            
            valueInput.onchange = (e) => ruleData.value = e.target.value;
            
        } else if (ruleData.type === 'date' || ruleData.type === 'datetime') {
             valueInput = document.createElement('input');
             valueInput.type = 'date';
             valueInput.className = 'form-control form-control-sm';
             valueInput.value = ruleData.value;
             valueInput.onchange = (e) => ruleData.value = e.target.value;
        } else if (ruleData.type === 'integer' || ruleData.type === 'number' || ruleData.type === 'decimal') {
             valueInput = document.createElement('input');
             valueInput.type = 'number';
             valueInput.className = 'form-control form-control-sm';
             valueInput.value = ruleData.value;
             valueInput.onchange = (e) => ruleData.value = e.target.value;
        } else if (ruleData.type === 'boolean') {
             valueInput = document.createElement('select');
             valueInput.className = 'form-select form-select-sm';
             valueInput.innerHTML = '<option value="1">Yes</option><option value="0">No</option>';
             valueInput.value = ruleData.value;
             valueInput.onchange = (e) => ruleData.value = e.target.value;
        } else {
             valueInput = document.createElement('input');
             valueInput.type = 'text';
             valueInput.className = 'form-control form-control-sm';
             valueInput.placeholder = 'Value...';
             valueInput.value = ruleData.value || '';
             valueInput.onchange = (e) => ruleData.value = e.target.value;
        }

        // Actions Container
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'ms-auto d-flex gap-1';

        // Wrap in Group Button
        const wrapBtn = document.createElement('button');
        wrapBtn.className = 'btn btn-sm btn-outline-secondary';
        wrapBtn.title = 'Wrap in Group ( )';
        wrapBtn.innerHTML = '<i class="bi bi-parentheses"></i>';
        wrapBtn.onclick = () => {
            // Create a new group containing this rule
            const newGroup = {
                condition: 'AND',
                rules: [ruleData] // Move current rule inside
            };
            // Replace current rule with new group
            parentRules[index] = newGroup;
            renderBuilder();
        };

        // Remove Button
        const removeBtn = document.createElement('button');
        removeBtn.className = 'btn btn-sm btn-close';
        removeBtn.onclick = () => {
            parentRules.splice(index, 1);
            renderBuilder();
        };

        row.appendChild(fieldSelect);
        row.appendChild(opSelect);
        if(valueInput.type !== 'hidden') row.appendChild(valueInput);
        
        actionsDiv.appendChild(wrapBtn);
        actionsDiv.appendChild(removeBtn);
        row.appendChild(actionsDiv);
        
        return row;
    }

    function getOperators(type) {
        const common = [
            { val: 'equal', label: 'Is' },
            { val: 'not_equal', label: 'Is Not' },
            { val: 'is_empty', label: 'Is Empty' },
            { val: 'is_not_empty', label: 'Is Not Empty' },
        ];
        
        if (type === 'string' || type === 'text') {
            return [
                ...common,
                { val: 'contains', label: 'Contains' },
                { val: 'not_contains', label: 'Not Contains' },
                { val: 'starts_with', label: 'Starts With' },
                { val: 'ends_with', label: 'Ends With' },
                { val: 'in', label: 'In List (comma)' },
            ];
        }
        
        if (['integer', 'number', 'decimal', 'float', 'date', 'datetime'].includes(type)) {
            return [
                ...common,
                { val: 'greater', label: 'Greater Than' },
                { val: 'less', label: 'Less Than' },
                { val: 'greater_or_equal', label: '>= ' },
                { val: 'less_or_equal', label: '<= ' },
                { val: 'between', label: 'Between' },
            ];
        }

        return common;
    }

    // Initialize Builder
    renderBuilder();
    
    document.getElementById('resetRules').onclick = () => {
        queryRules = { condition: 'AND', rules: [] };
        renderBuilder();
    };


    // --- COLUMN SELECTION & SORTING LOGIC ---
    
    const selectedList = document.getElementById('selectedColumnsList');
    const emptyMsg = document.getElementById('emptySelectionMsg');
    
    // SortableJS initialization
    new Sortable(selectedList, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: function (evt) {
            // Reordering happens automatically in DOM. No extra logic needed unless we sync state.
        }
    });

    function addColumnToSortedList(key, label) {
        if (document.querySelector(`.selected-column-item[data-id="${key}"]`)) return;

        const item = document.createElement('div');
        item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2 selected-column-item';
        item.dataset.id = key;
        item.innerHTML = `
            <span class="small"><i class="bi bi-grip-vertical text-muted me-2"></i>${label}</span>
            <button type="button" class="btn-close btn-sm" aria-label="Remove"></button>
        `;
        
        // Remove handler
        item.querySelector('.btn-close').addEventListener('click', function() {
            item.remove();
            const checkbox = document.getElementById(`col_${key.replace('.', '_')}`);
            if (checkbox) checkbox.checked = false;
            updateEmptyMsg();
        });

        // Add before empty msg or append
        selectedList.appendChild(item);
        updateEmptyMsg();
    }

    function removeColumnFromSortedList(key) {
        const item = document.querySelector(`.selected-column-item[data-id="${key}"]`);
        if (item) item.remove();
        updateEmptyMsg();
    }

    function updateEmptyMsg() {
        const hasItems = selectedList.querySelectorAll('.selected-column-item').length > 0;
        emptyMsg.style.display = hasItems ? 'none' : 'block';
    }

    // Sync Checkboxes -> List
    document.querySelectorAll('.column-checkbox').forEach(cb => {
        // Initial state
        if (cb.checked) {
            addColumnToSortedList(cb.value, cb.dataset.label);
        }

        // Change handler
        cb.addEventListener('change', function() {
            if (this.checked) {
                addColumnToSortedList(this.value, this.dataset.label);
            } else {
                removeColumnFromSortedList(this.value);
            }
        });
    });

    // Column Search
    document.getElementById('columnSearch').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.column-item').forEach(item => {
            const label = item.dataset.label;
            if (label.includes(term)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
        
        // Hide empty groups
        document.querySelectorAll('.column-group').forEach(group => {
            const hasVisible = group.querySelectorAll('.column-item[style="display: block;"]').length > 0;
            const hasDefault = group.querySelectorAll('.column-item:not([style*="display: none"])').length > 0;
            group.style.display = (hasVisible || hasDefault) ? 'block' : 'none';
        });
    });

    function getSelectedColumns() {
        // Get columns from the SORTED list
        return Array.from(document.querySelectorAll('.selected-column-item')).map(el => el.dataset.id);
    }
    
    function getPdfSettings() {
        return {
            title: document.getElementById('reportTitle').value || 'Job Report',
            title_align: document.getElementById('titleAlign').value,
            orientation: document.getElementById('pageOrientation').value,
            header: document.getElementById('headerText').value,
            footer: document.getElementById('footerText').value,
        };
    }
    
    // Updated Query String Builder
    function buildQueryParams(format) {
        const params = new URLSearchParams();
        
        // Columns
        getSelectedColumns().forEach(c => params.append('columns[]', c));
        
        // Rules
        params.append('query_rules', JSON.stringify(queryRules));
        
        // PDF Settings
        if (format === 'pdf' || format === 'print') {
            const settings = getPdfSettings();
            Object.entries(settings).forEach(([k, v]) => { if (v) params.append(k, v); });
        }
        
        if (format) params.append('format', format);
        
        return params.toString();
    }

    // Preview
    document.getElementById('previewBtn').addEventListener('click', async function() {
        const columns = getSelectedColumns();
        if (columns.length === 0) { alert('Please select at least one column'); return; }
        
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';
        
        try {
            const response = await fetch('{{ route("reports.preview") }}?' + buildQueryParams());
            const data = await response.json();
            
            if (data.success) {
                const header = document.getElementById('previewHeader');
                header.innerHTML = Object.values(data.columns).map(c => `<th>${c.label}</th>`).join('');
                
                const body = document.getElementById('previewBody');
                if (data.data.length > 0) {
                    body.innerHTML = data.data.map(row => 
                        '<tr>' + Object.values(row).map(v => `<td>${v}</td>`).join('') + '</tr>'
                    ).join('');
                } else {
                    body.innerHTML = '<tr><td colspan="100" class="text-center text-muted py-3">No data found</td></tr>';
                }
                document.getElementById('totalCount').textContent = data.total + ' records';
            }
        } catch (error) {
            console.error(error);
            alert('Error loading preview');
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-eye me-1"></i>Preview';
        }
    });

    // Exports
    document.getElementById('exportExcel').onclick = () => {
        if (getSelectedColumns().length === 0) return alert('Select columns first');
        window.location.href = '{{ route("reports.export") }}?' + buildQueryParams('xlsx');
    };
    document.getElementById('exportCsv').onclick = () => {
        if (getSelectedColumns().length === 0) return alert('Select columns first');
        window.location.href = '{{ route("reports.export") }}?' + buildQueryParams('csv');
    };
    document.getElementById('exportPdf').onclick = () => {
        if (getSelectedColumns().length === 0) return alert('Select columns first');
        window.open('{{ route("reports.export") }}?' + buildQueryParams('pdf'), '_blank');
    };
    document.getElementById('exportPrint').onclick = () => {
        if (getSelectedColumns().length === 0) return alert('Select columns first');
        window.open('{{ route("reports.export") }}?' + buildQueryParams('print'), '_blank');
    };

    // Select All/None
    document.getElementById('selectAllCols').onclick = () => {
        document.querySelectorAll('.column-checkbox').forEach(cb => {
            if (!cb.checked) {
                cb.checked = true;
                addColumnToSortedList(cb.value, cb.dataset.label);
            }
        });
    };
    document.getElementById('selectNoneCols').onclick = () => {
        document.querySelectorAll('.column-checkbox').forEach(cb => {
            cb.checked = false;
            removeColumnFromSortedList(cb.value);
        });
    };

    // Save Report
    document.getElementById('saveReportBtn').onclick = async function() {
        const name = document.getElementById('reportName').value.trim();
        if (!name) return alert('Enter report name');
        if (getSelectedColumns().length === 0) return alert('Select columns');
        
        try {
            const response = await fetch('{{ route("reports.save") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ name, columns: getSelectedColumns(), filters: queryRules })
            });
            const data = await response.json();
            if (data.success) location.reload();
        } catch (error) {
            alert('Error saving report');
        }
    };

    // Load Report
    document.querySelectorAll('.load-report').forEach(link => {
        link.addEventListener('click', async function(e) {
            e.preventDefault();
            try {
                const response = await fetch(`/reports/${this.dataset.id}/load`);
                const data = await response.json();
                if (data.success) {
                    // Update Columns: clear list, then add in Saved order
                    document.getElementById('selectNoneCols').click();
                    
                    data.report.columns.forEach(col => {
                        const cb = document.getElementById('col_' + col.replace('.', '_'));
                        if (cb) {
                            cb.checked = true;
                            addColumnToSortedList(col, cb.dataset.label);
                        }
                    });
                    
                    // Load Rules
                    if (data.report.filters) {
                         if (data.report.filters.condition) {
                             queryRules = data.report.filters;
                         } else {
                             queryRules = { condition: 'AND', rules: [] };
                         }
                         renderBuilder();
                    }
                    
                    document.getElementById('previewBtn').click();
                }
            } catch (error) {
                alert('Error loading report');
            }
        });
    });

    // Delete Report
    document.querySelectorAll('.delete-report').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.stopPropagation();
            if(!confirm('Delete?')) return;
            await fetch(`/reports/${this.dataset.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken } });
            location.reload();
        });
    });
});
</script>
@endpush
@endsection
