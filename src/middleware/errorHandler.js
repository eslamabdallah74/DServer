function errorHandler(err, req, res, next) {
  console.error('[Server Error]:', err);

  const status = err.status || 500;
  const message = err.message || 'حدث خطأ داخلي في الخادم.';

  res.status(status).json({
    error: err.code || 'INTERNAL_SERVER_ERROR',
    message,
    ...(process.env.NODE_ENV === 'development' ? { stack: err.stack } : {}),
  });
}

module.exports = errorHandler;
