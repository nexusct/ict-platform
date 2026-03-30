package com.ict.platform.service;

import com.ict.platform.dto.request.AuthRequest;
import com.ict.platform.dto.request.RegisterRequest;
import com.ict.platform.dto.response.AuthResponse;
import com.ict.platform.dto.response.UserResponse;

public interface AuthService {

    AuthResponse login(AuthRequest request);

    AuthResponse register(RegisterRequest request);

    AuthResponse refreshToken(String refreshToken);

    UserResponse getCurrentUser(String username);
}
