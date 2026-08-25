class ResponseHandler {
  static success(res, data = {}, status = 200) {
    return res.status(status).json(data);
  }

  static created(res, data = {}) {
    return res.status(201).json(data);
  }

  static badRequest(res, message = 'بيانات غير صالحة', error = 'INVALID_INPUT') {
    return res.status(400).json({ error, message });
  }

  static unauthorized(res, message = 'الرجاء تسجيل الدخول أولاً للوصول إلى لوحة التحكم.', error = 'UNAUTHORIZED') {
    return res.status(401).json({ error, message });
  }

  static forbidden(res, message = 'جلسة الدخول غير صالحة أو انتهت مدتها.', error = 'INVALID_TOKEN') {
    return res.status(403).json({ error, message });
  }

  static notFound(res, message = 'العنصر المطلوبة غير موجود.', error = 'NOT_FOUND') {
    return res.status(404).json({ error, message });
  }

  static serverError(res, message = 'حدث خطأ في الخادم.', error = 'SERVER_ERROR') {
    return res.status(500).json({ error, message });
  }
}

module.exports = ResponseHandler;
