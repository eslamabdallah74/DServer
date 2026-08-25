const ResponseHandler = require('../core/ResponseHandler');

class ErrorMiddleware {
  static handle(err, req, res, next) {
    console.error('[Server Exception]:', err);
    const message = err.message || 'حدث خطأ داخلي في الخادم.';
    return ResponseHandler.serverError(res, message, err.code || 'INTERNAL_SERVER_ERROR');
  }
}

module.exports = ErrorMiddleware;
