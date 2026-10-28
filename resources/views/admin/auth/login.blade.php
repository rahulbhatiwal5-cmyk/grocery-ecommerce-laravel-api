<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login · GreenBasket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 460px;">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center fs-4 fw-bold" style="width: 54px; height: 54px;">G</div>
                <h1 class="h3 mt-3 mb-1">GreenBasket Admin</h1>
                <p class="text-secondary mb-0">Sign in with an administrator account.</p>
            </div>
            <form action="{{ route('admin.login.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                    <label class="form-check-label" for="remember">Keep me signed in</label>
                </div>
                <button class="btn btn-success w-100" type="submit">Sign in</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
