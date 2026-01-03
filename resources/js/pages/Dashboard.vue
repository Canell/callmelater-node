<template>
    <div class="container py-4">
        <h2 class="mb-4">My Projects</h2>

        <div class="row">
            <!-- Project cards will go here -->
            <div class="col-md-4 mb-3">
                <div class="card card-cml p-3">
                    <div class="d-flex align-items-start">
                        <span class="status-indicator ok me-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                            </svg>
                        </span>
                        <div>
                            <h5 class="mb-1">Demo Project</h5>
                            <small class="text-muted">0 actions, 0 integrations</small><br>
                            <small class="text-muted">demo@example.com</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Project card -->
            <div class="col-md-4 mb-3">
                <div class="card card-new-project p-3 h-100 d-flex align-items-center justify-content-center" role="button">
                    <span class="text-muted">New Project...</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Dashboard',
};
</script>
