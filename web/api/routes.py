# Basic REST api
from flask import Flask, request, jsonify
from flask.ext.sqlalchemy import SQLAlchemy

app = Flask(__name__)
# Database configuration
app.config['SQLALCHEMY_DATABASE_URI'] = 'mysql+mysqlconnector://pk:protokoll@127.0.0.1/pk?unix_socket=/run/mysqld/mysqld.sock'
# Enable debugging to log errors to
# /var/log/uwsgi/app/protokollen.log (or whatever)
app.config['DEBUG'] = True
db = SQLAlchemy(app)

class Entities(db.Model):
	__tablename__ = 'entities'
	id = db.Column(db.Integer, primary_key = True)
	org = db.Column(db.String(255))
	org_group = db.Column(db.String(255))
	cat = db.Column(db.String(255))
	domain = db.Column(db.String(255))
	domain_email = db.Column(db.String(255))
	url = db.Column(db.String(255))
	created = db.Column(db.DateTime)
	updated = db.Column(db.DateTime)

class Logs(db.Model):
	__tablename__ = 'logs'
	id = db.Column(db.Integer, primary_key = True)
	service_id = db.Column(db.Integer)
	json_id = db.Column(db.Integer)
	hostname = db.Column(db.String(255))
	service = db.Column(db.String(64))
	log = db.Column(db.Text)
	created = db.Column(db.DateTime)

class Services(db.Model):
	__tablename__ = 'services'
	id = db.Column(db.Integer, primary_key = True)
	entity_id = db.Column(db.Integer)
	entity_domain = db.Column(db.String(255))
	service_type = db.Column(db.String(255))
	service_name = db.Column(db.String(255))
	service_desc = db.Column(db.String(255))
	created = db.Column(db.DateTime)
	updated = db.Column(db.DateTime)

class SvcGroupMap(db.Model):
	__tablename__ = 'svc_group_map'
	id = db.Column(db.Integer, primary_key = True)
	service_id = db.Column(db.Integer)
	svc_group_id = db.Column(db.Integer)
	entry_type = db.Column(db.Enum('current', 'revision'))
	created = db.Column(db.DateTime)
	updated = db.Column(db.DateTime)

@app.route('/')
def index():
	raise Exception('This is only here to cause an internal server error')

@app.route('/entities', methods=['GET'])
@app.route('/entities/<int:id>', methods=['GET'])
def entities(id=None):
	if request.method == 'GET':
		if id:
			res = Entities.query.filter_by(id=id).limit(100).offset(0).all()
		else:
			res = Entities.query.limit(100).offset(0).all()
		
		list = []
		for r in res:
			d = {
			'id': r.id,
			'org': r.org,
			'orgGroup': r.org_group,
			'domain': r.domain,
			'domainEmail': r.domain_email,
			'url': r.url,
			'created': r.created,
			'updated': r.updated
			}
			list.append(d)

		return jsonify(items=list)

@app.route('/services', methods=['GET'])
@app.route('/services/<int:id>', methods=['GET'])
def services(id=None):
	if request.method == 'GET':
		if id:
			res = Services.query.filter_by(id=id).limit(100).offset(0).all()
		else:
			res = Services.query.limit(100).offset(0).all()
		
		list = []
		for r in res:
			d = {
			'id': r.id,
			'entityId': r.entity_id,
			'entityDomain': r.entity_domain,
			'serviceType': r.service_type,
			'serviceName': r.service_name,
			'serviceDesc': r.service_desc,
			'created': r.created,
			'updated': r.updated
			}
			list.append(d)

		return jsonify(items=list)

@app.route('/logs', methods=['GET'])
@app.route('/logs/<int:id>', methods=['GET'])
def logs(id=None):
	if request.method == 'GET':
		if id:
			res = Logs.query.filter_by(id=id).limit(100).offset(0).all()
		else:
			res = Logs.query.limit(100).offset(0).all()
		
		list = []
		for r in res:
			d = {
			'id': r.id,
			'serviceId': r.service_id,
			'hostname': r.hostname,
			'service': r.service,
			'log': r.log,
			'created': r.created,
			}
			list.append(d)

		return jsonify(items=list)

if __name__ == '__main__':
	app.run(debug=True, host='127.0.0.1')
