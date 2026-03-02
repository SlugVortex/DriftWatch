<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Trezo - Laravel Admin Dashboard Template</title>
        <!-- Styles -->
        @include('partials.styles')
    </head>
    <body class="boxed-size bg-white">
        @include('partials.preloader')

        <div class="container">
            <div class="main-content d-flex flex-column p-0">
                <div class="m-auto m-1230">
                    <div class="row align-items-center">
                        <div class="col-lg-6 d-none d-lg-block">
                            <img src="assets/images/register.jpg" class="rounded-3" alt="register">
                        </div>
                        <div class="col-lg-6">
                            <div class="mw-480 ms-lg-auto">
                                <a href="index" class="d-inline-block mb-4">
                                    <img src="assets/images/logo.svg" class="rounded-3 for-light-logo" alt="login">
                                    <img src="assets/images/white-logo.svg" class="rounded-3 for-dark-logo" alt="login">
                                </a>
                                <h3 class="fs-28 mb-2">Register to Trezo Dashboard</h3>
                                <p class="fw-medium fs-16 mb-4">Register with social account or enter your details</p>
                                <div class="row justify-content-center">
                                    <div class="col-lg-4 col-sm-4">
                                        <a href="https://www.google.com/" target="_blank" class="btn btn-outline-secondary bg-transparent w-100 py-2 hover-bg mb-4" style="border-color: #D6DAE1;">
                                            <img src="assets/images/google.svg" alt="google">
                                        </a>
                                    </div>
                                    <div class="col-lg-4 col-sm-4">
                                        <a href="https://www.facebook.com/" target="_blank" class="btn btn-outline-secondary bg-transparent w-100 py-2 hover-bg mb-4" style="border-color: #D6DAE1;">
                                            <img src="assets/images/facebook2.svg" alt="facebook2">
                                        </a>
                                    </div>
                                    <div class="col-lg-4 col-sm-4">
                                        <a href="https://www.apple.com/" target="_blank" class="btn btn-outline-secondary bg-transparent w-100 py-2 hover-bg mb-4" style="border-color: #D6DAE1;">
                                            <img src="assets/images/apple.svg" alt="apple">
                                        </a>
                                    </div>
                                </div>
                                <form>
                                    <div class="form-group mb-3">
                                        <label class="label text-secondary">Full Name</label>
                                        <input type="text" class="form-control" placeholder="Enter your full name">
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="label text-secondary">Email Address</label>
                                        <input type="email" class="form-control" placeholder="example@trezo.com">
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="label text-secondary">Password</label>
                                        <input type="password" class="form-control" placeholder="Type password">
                                    </div>
                                    <div class="form-group mb-3">
                                        <button type="submit" class="btn btn-primary fw-medium py-2 px-3 w-100">
                                            <div class="d-flex align-items-center justify-content-center py-1">
                                                <i class="material-symbols-outlined text-white fs-20 me-2">person_4</i>
                                                <span>Register</span>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="form-group">
                                        <p>By confirming your email, you agree to our <a href="terms-conditions"class="fw-medium text-decoration-none">Terms of Service</a> and that you have read and understood our <a href="privacy-policy"class="fw-medium text-decoration-none">Privacy Policy</a>.</p>
                                        <p>Already have an account. <a href="login" class="fw-medium text-primary text-decoration-none">Log In</a></p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button class="theme-settings-btn p-0 border-0 bg-transparent position-absolute" style="right: 30px; bottom: 30px;" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasScrolling" aria-controls="offcanvasScrolling">
            <i class="material-symbols-outlined bg-primary wh-35 lh-35 text-white rounded-1" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Click On Theme Settings">settings</i>
        </button>

        
        @include('partials.theme_settings')
        @include('partials.scripts')
    </body>
</html>
