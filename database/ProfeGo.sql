CREATE DATABASE ProfeGo;
GO
 
USE ProfeGo;
GO
 
CREATE TABLE usuarios (
  id_usuario     INT IDENTITY(1,1) NOT NULL,
  nombres        VARCHAR(100) NOT NULL,
  apellidos      VARCHAR(100) NOT NULL,
  correo         VARCHAR(150) NOT NULL,
  documento      VARCHAR(30)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  rol            VARCHAR(20)  NOT NULL,
  telefono       VARCHAR(20)  NULL,
  foto_perfil    VARCHAR(255) NULL,
  estado         VARCHAR(20)  NOT NULL,
  fecha_registro DATETIME      NOT NULL,
  PRIMARY KEY (id_usuario),
  UNIQUE (correo),
  UNIQUE (documento)
);
GO
 
CREATE TABLE perfiles_docente (
  id_docente          INT NOT NULL,
  descripcion         VARCHAR(1000) NULL,
  experiencia_anios   INT NOT NULL,
  estado_verificacion VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_docente),
  FOREIGN KEY (id_docente) REFERENCES usuarios (id_usuario)
);
GO
 
CREATE TABLE asignaturas (
  id_asignatura INT IDENTITY(1,1) NOT NULL,
  nombre        VARCHAR(100) NOT NULL,
  descripcion   VARCHAR(500) NULL,
  activo        INT NOT NULL,
  PRIMARY KEY (id_asignatura),
  UNIQUE (nombre)
);
GO
 
CREATE TABLE servicios_docente (
  id_servicio      INT IDENTITY(1,1) NOT NULL,
  id_docente       INT NOT NULL,
  id_asignatura    INT NOT NULL,
  tema             VARCHAR(150) NOT NULL,
  nivel_educativo  VARCHAR(30)  NOT NULL,
  grado_desde      INT NULL,
  grado_hasta      INT NULL,
  precio_hora      INT NOT NULL,
  duracion_minutos INT NOT NULL,
  modalidad        VARCHAR(20) NOT NULL,
  activo           INT NOT NULL,
  fecha_creacion   DATETIME NOT NULL,
  PRIMARY KEY (id_servicio),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente),
  FOREIGN KEY (id_asignatura) REFERENCES asignaturas (id_asignatura)
);
GO
 
CREATE TABLE disponibilidad_docente (
  id_disponibilidad INT IDENTITY(1,1) NOT NULL,
  id_docente        INT NOT NULL,
  dia_semana        INT NOT NULL,
  hora_inicio       TIME NOT NULL,
  hora_fin          TIME NOT NULL,
  activo            INT NOT NULL,
  PRIMARY KEY (id_disponibilidad),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente)
);
GO
 
CREATE TABLE solicitudes_tiempo_real (
  id_solicitud     INT IDENTITY(1,1) NOT NULL,
  id_estudiante    INT NOT NULL,
  id_asignatura    INT NOT NULL,
  tema             VARCHAR(150) NOT NULL,
  nivel_educativo  VARCHAR(30)  NOT NULL,
  grado            INT NULL,
  descripcion      VARCHAR(1000) NULL,
  modalidad        VARCHAR(20) NOT NULL,
  presupuesto      INT NULL,
  duracion_minutos INT NOT NULL,
  ubicacion        VARCHAR(255) NULL,
  fecha_deseada    DATETIME NOT NULL,
  estado           NVARCHAR(20) NOT NULL,
  fecha_creacion   DATETIME NOT NULL,
  PRIMARY KEY (id_solicitud),
  FOREIGN KEY (id_estudiante) REFERENCES usuarios (id_usuario),
  FOREIGN KEY (id_asignatura) REFERENCES asignaturas (id_asignatura)
);
GO
 
CREATE TABLE ofertas_tiempo_real (
  id_oferta        INT IDENTITY(1,1) NOT NULL,
  id_solicitud     INT NOT NULL,
  id_docente       INT NOT NULL,
  precio_ofertado  INT NOT NULL,
  duracion_minutos INT NOT NULL,
  mensaje          VARCHAR(500) NULL,
  estado           VARCHAR(20) NOT NULL,
  fecha_oferta     DATETIME NOT NULL,
  PRIMARY KEY (id_oferta),
  FOREIGN KEY (id_solicitud) REFERENCES solicitudes_tiempo_real (id_solicitud),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente)
);
GO
 
CREATE TABLE reservas (
  id_reserva      INT IDENTITY(1,1) NOT NULL,
  id_estudiante   INT NOT NULL,
  id_docente      INT NOT NULL,
  id_servicio     INT NULL,
  id_solicitud    INT NULL,
  id_oferta       INT NULL,
  fecha_inicio    DATETIME NOT NULL,
  fecha_fin       DATETIME NOT NULL,
  modalidad       VARCHAR(20) NOT NULL,
  ubicacion       VARCHAR(255) NULL,
  enlace_virtual  VARCHAR(500) NULL,
  precio_acordado INT NOT NULL,
  estado          VARCHAR(20) NOT NULL,
  fecha_creacion  DATETIME NOT NULL,
  PRIMARY KEY (id_reserva),
  FOREIGN KEY (id_estudiante) REFERENCES usuarios (id_usuario),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente),
  FOREIGN KEY (id_servicio) REFERENCES servicios_docente (id_servicio),
  FOREIGN KEY (id_solicitud) REFERENCES solicitudes_tiempo_real (id_solicitud),
  FOREIGN KEY (id_oferta) REFERENCES ofertas_tiempo_real (id_oferta)
);
GO
 
-- UNIQUE filtrado: permite muchos NULL en id_oferta pero no ofertas repetidas
CREATE UNIQUE INDEX uq_reservas_oferta
  ON reservas (id_oferta)
  WHERE id_oferta IS NOT NULL;
GO
 
CREATE TABLE resenas (
  id_resena      INT IDENTITY(1,1) NOT NULL,
  id_reserva     INT NOT NULL,
  id_estudiante  INT NOT NULL,
  id_docente     INT NOT NULL,
  calificacion   INT NOT NULL,
  comentario     VARCHAR(1000) NULL,
  estado         VARCHAR(20) NOT NULL,
  fecha_creacion DATETIME NOT NULL,
  PRIMARY KEY (id_resena),
  UNIQUE (id_reserva),
  FOREIGN KEY (id_reserva) REFERENCES reservas (id_reserva),
  FOREIGN KEY (id_estudiante) REFERENCES usuarios (id_usuario),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente)
);
GO
 
CREATE TABLE conversaciones (
  id_conversacion INT IDENTITY(1,1) NOT NULL,
  id_estudiante   INT NOT NULL,
  id_docente      INT NOT NULL,
  id_reserva      INT NULL,
  id_solicitud    INT NULL,
  fecha_creacion  DATETIME NOT NULL,
  PRIMARY KEY (id_conversacion),
  FOREIGN KEY (id_estudiante) REFERENCES usuarios (id_usuario),
  FOREIGN KEY (id_docente) REFERENCES perfiles_docente (id_docente),
  FOREIGN KEY (id_reserva) REFERENCES reservas (id_reserva),
  FOREIGN KEY (id_solicitud) REFERENCES solicitudes_tiempo_real (id_solicitud)
);
GO
 
CREATE TABLE mensajes (
  id_mensaje      INT IDENTITY(1,1) NOT NULL,
  id_conversacion INT NOT NULL,
  id_remitente    INT NOT NULL,
  contenido       VARCHAR(1000) NULL,
  tipo            VARCHAR(20) NOT NULL,
  archivo_url     VARCHAR(500) NULL,
  fecha_envio     DATETIME NOT NULL,
  leido_en        DATETIME NULL,
  PRIMARY KEY (id_mensaje),
  FOREIGN KEY (id_conversacion) REFERENCES conversaciones (id_conversacion),
  FOREIGN KEY (id_remitente) REFERENCES usuarios (id_usuario)
);
GO
 
CREATE TABLE configuraciones_usuario (
  id_usuario             INT NOT NULL,
  notificaciones_activas INT NOT NULL,
  tema_visual            NVARCHAR(20) NOT NULL,
  idioma                 NVARCHAR(10) NOT NULL,
  fecha_actualizacion    DATETIME NOT NULL,
  PRIMARY KEY (id_usuario),
  FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
);
GO