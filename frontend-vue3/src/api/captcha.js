// 验证码接口
import { get } from './request'

// GET /api/captcha → { code:0, data:{ captcha_id, image } }
// image 为 data:image/png;base64,...
export const getCaptcha = () => get('/captcha')
