{{-- resources/views/partials/theme_settings.blade.php --}}
{{-- DriftWatch customization panel — dark mode + sidebar style --}}

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
    <div class="offcanvas-header border-bottom p-4">
        <h5 class="offcanvas-title fs-18 mb-0" id="offcanvasRightLabel">Create Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4">
        <form>
            <div class="form-group mb-4">
                <label class="label">Task ID</label>
                <input type="text" class="form-control text-dark" placeholder="Task ID">
            </div>
            <div class="form-group mb-4">
                <label class="label">Task Title</label>
                <input type="text" class="form-control text-dark" placeholder="Task Title">
            </div>
            <div class="form-group mb-4">
                <label class="label">Assigned To</label>
                <input type="text" class="form-control text-dark" placeholder="Assigned To">
            </div>
            <div class="form-group mb-4">
                <label class="label">Due Date</label>
                <input type="date" class="form-control text-dark">
            </div>
            <div class="form-group mb-4">
                <label class="label">Priority</label>
                <select class="form-select form-control text-dark" aria-label="Default select example">
                    <option selected>High</option>
                    <option value="1">Low</option>
                    <option value="2">Medium</option>
                </select>
            </div>

            <div class="form-group mb-4">
                <label class="label">Status</label>
                <select class="form-select form-control text-dark" aria-label="Default select example">
                    <option selected>Finished</option>
                    <option value="1">Pending</option>
                    <option value="2">In Progress</option>
                    <option value="3">Cancelled</option>
                </select>
            </div>

            <div class="form-group mb-4">
                <label class="label">Action</label>
                <select class="form-select form-control text-dark" aria-label="Default select example">
                    <option selected>Yes</option>
                    <option value="1">No</option>
                </select>
            </div>

            <div class="form-group d-flex gap-3">
                <button class="btn btn-primary text-white fw-semibold py-2 px-2 px-sm-3">
                    <span class="py-sm-1 d-block">
                        <i class="ri-add-line text-white"></i>
                        <span>Create Task</span>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>


<div class="offcanvas offcanvas-end bg-white" data-bs-scroll="true" data-bs-backdrop="true" tabindex="-1" id="offcanvasScrolling" aria-labelledby="offcanvasScrollingLabel" style="box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;">
    <div class="offcanvas-header bg-body-bg py-3 px-4">
        <h5 class="offcanvas-title fs-18 d-flex align-items-center gap-2" id="offcanvasScrollingLabel">
            <span class="material-symbols-outlined" style="font-size: 20px;">palette</span>
            Customization
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4">
        <p class="text-secondary fs-13 mb-4">Personalize the look and feel of your DriftWatch dashboard.</p>

        {{-- Dark / Light Mode --}}
        <div class="mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined text-warning" style="font-size: 20px;">dark_mode</span>
                    <div>
                        <h6 class="fs-14 fw-semibold mb-0">Dark Mode</h6>
                        <small class="text-secondary fs-12">Switch between light and dark themes</small>
                    </div>
                </div>
            </div>
            <div class="settings-btn rtl-btn mt-2">
                <label id="switch" class="switch">
                    <input type="checkbox" onchange="toggleTheme()" id="slider">
                    <span class="slider round">Click To Toggle</span>
                </label>
            </div>
        </div>

        {{-- Sidebar Style --}}
        <div class="mb-4 pb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined text-info" style="font-size: 20px;">side_navigation</span>
                    <div>
                        <h6 class="fs-14 fw-semibold mb-0">Sidebar Style</h6>
                        <small class="text-secondary fs-12">Toggle sidebar between light and dark</small>
                    </div>
                </div>
            </div>
            <button class="sidebar-light-dark settings-btn sidebar-dark-btn mt-2" id="sidebar-light-dark">
                Click To <span class="dark1">Dark</span> <span class="light1">Light</span>
            </button>
        </div>
    </div>
</div>
