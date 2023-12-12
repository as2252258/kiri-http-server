<?php


namespace Kiri\Server;


/**
 * Class Constant
 * @package Server
 */
class Constant
{

	const string START = 'Start';
	const string SHUTDOWN = 'Shutdown';
	const string BEFORE_SHUTDOWN = 'beforeShutdown';
	const string WORKER_START = 'WorkerStart';
	const string WORKER_STOP = 'WorkerStop';
	const string WORKER_EXIT = 'WorkerExit';
	const string CONNECT = 'Connect';
	const string HANDSHAKE = 'handshake';
	const string OPEN = 'open';
	const string DISCONNECT = 'disconnect';
	const string MESSAGE = 'message';
	const string RECEIVE = 'Receive';
	const string PACKET = 'Packet';
	const string REQUEST = 'request';
	const string CLOSE = 'Close';
	const string TASK = 'Task';
	const string FINISH = 'Finish';
	const string PIPE_MESSAGE = 'PipeMessage';
	const string WORKER_ERROR = 'WorkerError';
	const string MANAGER_START = 'ManagerStart';
	const string MANAGER_STOP = 'ManagerStop';
	const string BEFORE_RELOAD = 'BeforeReload';
	const string AFTER_RELOAD = 'AfterReload';


	const string SERVER_TYPE_HTTP = 'http';
	const string SERVER_TYPE_WEBSOCKET = 'ws';
	const string SERVER_TYPE_TCP = 'tcp';
	const string SERVER_TYPE_UDP = 'udp';
	const string SERVER_TYPE_BASE = 'base';


	const string STATUS_404_MESSAGE = '<h2>HTTP 404 Not Found</h2><hr><i>Powered by Swoole</i>';
	const string STATUS_405_MESSAGE = '<h2>HTTP 405 Method allow</h2><hr><i>Powered by Swoole</i>';


    const string OPTION_REACTOR_NUM = 'reactor_num';
    const string OPTION_WORKER_NUM = 'worker_num';
    const string OPTION_MAX_REQUEST = 'max_request';
    const string OPTION_MAX_CONN = 'max_connection';
    const string OPTION_TASK_WORKER_NUM = 'task_worker_num';
    const string OPTION_TASK_IPC_MODE = 'task_ipc_mode';
    const string OPTION_TASK_MAX_REQUEST = 'task_max_request';
    const string OPTION_TASK_TMPDIR = 'task_tmpdir';
    const string OPTION_TASK_ENABLE_COROUTINE = 'task_enable_coroutine';
    const string OPTION_TASK_USE_OBJECT = 'task_use_object';
    const string OPTION_DISPATCH_MODE = 'dispatch_mode';
    const string OPTION_DISPATCH_FUNC = 'dispatch_func';
    const string OPTION_MESSAGE_QUEUE_KEY = 'message_queue_key';
    const string OPTION_DAEMONIZE = 'daemonize';
    const string OPTION_BACKLOG = 'backlog';
    const string OPTION_LOG_FILE = 'log_file';
    const string OPTION_LOG_LEVEL = 'log_level';
    const string OPTION_LOG_DATE_WITH_MICROSECONDS = 'log_date_with_microseconds';
    const string OPTION_LOG_ROTATION = 'log_rotation';
    const string OPTION_LOG_DATE_FORMAT = 'log_date_format';
    const string OPTION_OPEN_TCP_KEEPALIVE = 'open_tcp_keepalive';
    const string OPTION_HEARTBEAT_CHECK_INTERVAL = 'heartbeat_check_interval';
    const string OPTION_HEARTBEAT_IDLE_TIME = 'heartbeat_idle_time';
    const string OPTION_OPEN_EOF_CHECK = 'open_eof_check';
    const string OPTION_OPEN_EOF_SPLIT = 'open_eof_split';
    const string OPTION_PACKAGE_EOF = 'package_eof';
    const string OPTION_OPEN_LENGTH_CHECK = 'open_length_check';
    const string OPTION_PACKAGE_LENGTH_TYPE = 'package_length_type';
    const string OPTION_PACKAGE_LENGTH_FUNC = 'package_length_func';
    const string OPTION_PACKAGE_MAX_LENGTH = 'package_max_length';
    const string OPTION_OPEN_HTTP_PROTOCOL = 'open_http_protocol';
    const string OPTION_OPEN_MQTT_PROTOCOL = 'open_mqtt_protocol';
    const string OPTION_OPEN_REDIS_PROTOCOL = 'open_redis_protocol';
    const string OPTION_OPEN_WEBSOCKET_PROTOCOL = 'open_websocket_protocol';
    const string OPTION_OPEN_WEBSOCKET_CLOSE_FRAME = 'open_websocket_close_frame';
    const string OPTION_OPEN_TCP_NODELAY = 'open_tcp_nodelay';
    const string OPTION_OPEN_CPU_AFFINITY = 'open_cpu_affinity';
    const string OPTION_CPU_AFFINITY_IGNORE = 'cpu_affinity_ignore';
    const string OPTION_TCP_DEFER_ACCEPT = 'tcp_defer_accept';
    const string OPTION_SSL_CERT_FILE = 'ssl_cert_file';
    const string OPTION_SSL_KEY_FILE = 'ssl_key_file';
    const string OPTION_SSL_METHOD = 'ssl_method';
    const string OPTION_SSL_PROTOCOLS = 'ssl_protocols';
    const string OPTION_SSL_SNI_CERTS = 'ssl_sni_certs';
    const string OPTION_SSL_CIPHERS = 'ssl_ciphers';
    const string OPTION_SSL_VERIFY_PEER = 'ssl_verify_peer';
    const string OPTION_SSL_ALLOW_SELF_SIGNED = 'ssl_allow_self_signed';
    const string OPTION_SSL_CLIENT_CERT_FILE = 'ssl_client_cert_file';
    const string OPTION_SSL_COMPRESS = 'ssl_compress';
    const string OPTION_SSL_VERIFY_DEPTH = 'ssl_verify_depth';
    const string OPTION_SSL_PREFER_SERVER_CIPHERS = 'ssl_prefer_server_ciphers';
    const string OPTION_SSL_DHPARAM = 'ssl_dhparam';
    const string OPTION_SSL_ECDH_CURVE = 'ssl_ecdh_curve';
    const string OPTION_USER = 'user';
    const string OPTION_GROUP = 'group';
    const string OPTION_CHROOT = 'chroot';
    const string OPTION_PID_FILE = 'pid_file';
    const string OPTION_BUFFER_INPUT_SIZE = 'buffer_input_size';
    const string OPTION_BUFFER_OUTPUT_SIZE = 'buffer_output_size';
    const string OPTION_SOCKET_BUFFER_SIZE = 'socket_buffer_size';
    const string OPTION_ENABLE_UNSAFE_EVENT = 'enable_unsafe_event';
    const string OPTION_DISCARD_TIMEOUT_REQUEST = 'discard_timeout_request';
    const string OPTION_ENABLE_REUSE_PORT = 'enable_reuse_port';
    const string OPTION_ENABLE_DELAY_RECEIVE = 'enable_delay_receive';
    const string OPTION_RELOAD_ASYNC = 'reload_async';
    const string OPTION_MAX_WAIT_TIME = 'max_wait_time';
    const string OPTION_TCP_FASTOPEN = 'tcp_fastopen';
    const string OPTION_REQUEST_SLOWLOG_FILE = 'request_slowlog_file';
    const string OPTION_ENABLE_COROUTINE = 'enable_coroutine';
    const string OPTION_MAX_COROUTINE = 'max_coroutine';
    const string OPTION_SEND_YIELD = 'send_yield';
    const string OPTION_SEND_TIMEOUT = 'send_timeout';
    const string OPTION_HOOK_FLAGS = 'hook_flags';
    const string OPTION_BUFFER_HIGH_WATERMARK = 'buffer_high_watermark';
    const string OPTION_BUFFER_LOW_WATERMARK = 'buffer_low_watermark';
    const string OPTION_TCP_USER_TIMEOUT = 'tcp_user_timeout';
    const string OPTION_STATS_FILE = 'stats_file';
    const string OPTION_EVENT_OBJECT = 'event_object';
    const string OPTION_START_SESSION_ID = 'start_session_id';
    const string OPTION_SINGLE_THREAD = 'single_thread';
    const string OPTION_MAX_QUEUED_BYTES = 'max_queued_bytes';


}
